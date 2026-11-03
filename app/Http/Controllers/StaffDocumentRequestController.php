<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffDocumentRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('process-document-requests');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                Rule::in(array_keys(DocumentRequest::STATUSES)),
            ],
            'released_on' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status') ?? '';
        $releasedOn = $request->input('released_on') ?? '';

        $query = DocumentRequest::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('reference_number', 'like', "%{$search}%");
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            });

        if ($releasedOn !== '') {
            $localDay = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $releasedOn,
                'Asia/Manila'
            );

            $databaseTimezone = config('app.timezone', 'UTC');

            $query->where(
                'released_at',
                '>=',
                $localDay->setTimezone($databaseTimezone)
            )->where(
                'released_at',
                '<',
                $localDay->addDay()->setTimezone($databaseTimezone)
            );
        }

        $documentRequests = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('staff.documents.index', [
            'documentRequests' => $documentRequests,
            'search' => $search,
            'status' => $status,
            'releasedOn' => $releasedOn,
            'types' => DocumentRequest::TYPES,
            'statuses' => DocumentRequest::STATUSES,
        ]);
    }

    public function show(DocumentRequest $documentRequest): View
    {
        Gate::authorize('process-document-requests');

        $resident = Resident::query()->find($documentRequest->resident_id);
        $currentProfile = $resident ? $this->profile($resident) : null;

        $residentFingerprint = $currentProfile
            ? hash('sha256', json_encode($currentProfile))
            : '';

        $processingBlockReason = $this->processingBlockReason($resident);

        $events = DB::table('document_request_events as events')
            ->join('users', 'users.id', '=', 'events.actor_id')
            ->where('events.document_request_id', $documentRequest->id)
            ->select('events.*', 'users.name as actor_name')
            ->orderBy('events.id')
            ->get();

        return view('staff.documents.show', [
            'documentRequest' => $documentRequest,
            'currentProfile' => $currentProfile,
            'residentFingerprint' => $residentFingerprint,
            'processingBlockReason' => $processingBlockReason,
            'events' => $events,
            'types' => DocumentRequest::TYPES,
            'statuses' => DocumentRequest::STATUSES,
            'designatedCaptain' => $this->designatedCaptain(),
        ]);
    }

    public function updateStatus(
        Request $request,
        DocumentRequest $documentRequest
    ): RedirectResponse {
        Gate::authorize('process-document-requests');

        $request->merge([
            'note' => trim((string) $request->input('note', '')),
        ]);

        $data = $request->validate([
            'action' => ['required', 'in:review,approve,reject'],
            'note' => [
                'required_if:action,approve,reject',
                'nullable',
                'string',
                'min:10',
                'max:2000',
            ],
            'resident_fingerprint' => [
                'required_if:action,approve',
                'nullable',
                'string',
                'size:64',
            ],
        ]);

        if ($data['action'] !== 'review') {
            Gate::authorize('approve-document-requests');

            $request->validate([
                'decision_confirmed' => ['accepted'],
            ]);
        }

        DB::transaction(function () use ($request, $documentRequest, $data) {
            $item = DocumentRequest::query()
                ->lockForUpdate()
                ->findOrFail($documentRequest->id);

            $actor = $request->user()->fresh();

            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize('process-document-requests');

            $fromStatus = $item->status;

            if ($data['action'] === 'review') {
                if ($fromStatus !== 'submitted') {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Only submitted requests can be moved to review. '
                            . 'Refresh this page to see the latest status.',
                    ]);
                }

                $item->status = 'under_review';
                $note = 'Request moved to review.';
            } else {
                Gate::forUser($actor)->authorize('approve-document-requests');

                if ($fromStatus !== 'under_review') {
                    throw ValidationException::withMessages([
                        'status' =>
                            'Only requests under review can be approved or rejected.',
                    ]);
                }

                if (
                    (int) $item->requested_by === (int) $actor->id
                    || (
                        $actor->resident_id !== null
                        && (int) $actor->resident_id === (int) $item->resident_id
                    )
                ) {
                    throw ValidationException::withMessages([
                        'decision' =>
                            'You cannot decide a request you submitted or encoded, '
                            . 'or a request for your own resident record.',
                    ]);
                }

                $note = $data['note'];

                if ($data['action'] === 'approve') {
                    if (! array_key_exists($item->document_type, DocumentRequest::TYPES)) {
                        throw ValidationException::withMessages([
                            'document_type' => 'This document type is not supported.',
                        ]);
                    }

                    $resident = $this->lockEligibleResident($item, 'approval');

                    if ($item->source === 'online') {
                        $requester = User::query()
                            ->lockForUpdate()
                            ->findOrFail($item->requested_by);

                        if (
                            ! $requester->canAccessServices()
                            || $requester->role !== 'resident'
                            || (int) $requester->resident_id !== (int) $resident->id
                        ) {
                            throw ValidationException::withMessages([
                                'resident' =>
                                    'The online requester must have an approved '
                                    . 'account linked to this resident record.',
                            ]);
                        }
                    } elseif ($item->source !== 'walk_in') {
                        throw ValidationException::withMessages([
                            'source' => 'This request source is not supported.',
                        ]);
                    }

                    $profile = $this->profile($resident);
                    $fingerprint = hash('sha256', json_encode($profile));

                    if (! hash_equals($fingerprint, $data['resident_fingerprint'])) {
                        throw ValidationException::withMessages([
                            'resident' =>
                                'Resident details changed after this page was opened. '
                                . 'Refresh and review the updated information.',
                        ]);
                    }

                    $item->status = 'approved';
                    $item->approved_snapshot = $profile;
                    $item->rejection_reason = null;
                } else {
                    // Rejection remains available even if the resident is deceased.
                    $item->status = 'rejected';
                    $item->rejection_reason = $note;
                }
            }

            $updated = DocumentRequest::query()
                ->whereKey($item->id)
                ->where('status', $fromStatus)
                ->update($item->getDirty());

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Another staff member updated this request. '
                        . 'Refresh before continuing.',
                ]);
            }

            DB::table('document_request_events')->insert([
                'document_request_id' => $item->id,
                'actor_id' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => $item->status,
                'note' => $note,
                'occurred_at' => now(),
            ]);
        }, 3);

        return redirect()
            ->route('staff.documents.show', $documentRequest)
            ->with('success', 'Request status updated successfully.');
    }

    public function previewResidency(DocumentRequest $documentRequest)
    {
        Gate::authorize('process-document-requests');

        abort_unless(
            array_key_exists($documentRequest->document_type, DocumentRequest::TYPES),
            404
        );

        abort_unless(
            in_array($documentRequest->status, [
                'approved',
                'ready_for_release',
                'released',
            ], true),
            403,
            'The request must be approved before previewing the certificate.'
        );

        // Released documents remain accessible as historical records.
        if ($documentRequest->status !== 'released') {
            $resident = Resident::query()->find($documentRequest->resident_id);
            $reason = $this->processingBlockReason($resident);

            if ($reason !== null) {
                return redirect()
                    ->route('staff.documents.show', $documentRequest)
                    ->withErrors([
                        'preview' => 'Certificate preview is blocked. ' . $reason,
                    ]);
            }
        }

        $profile = $documentRequest->approved_snapshot;

        abort_unless(
            is_array($profile) && ! empty($profile['first_name']),
            409,
            'This request has no approved resident snapshot.'
        );

        $fullName = implode(' ', array_filter([
            $profile['first_name'] ?? '',
            $profile['middle_name'] ?? '',
            $profile['last_name'] ?? '',
        ], fn ($value) => trim((string) $value) !== ''));

        $fullAddress = implode(', ', array_filter([
            $profile['address'] ?? '',
            ! empty($profile['sitio']) ? 'Sitio ' . $profile['sitio'] : '',
            'Barangay Masipit',
            'Calapan City',
            'Oriental Mindoro',
        ], fn ($value) => trim((string) $value) !== ''));

        return response()
            ->view('staff.documents.residency', [
                'documentRequest' => $documentRequest,
                'fullName' => $fullName,
                'fullAddress' => $fullAddress,
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function prepareResidency(
        Request $request,
        DocumentRequest $documentRequest
    ): RedirectResponse {
        Gate::authorize('process-document-requests');

        $request->validate([
            'preparation_confirmed' => ['accepted'],
            'signatory_name' => ['prohibited'],
            'signatory_user_id' => ['prohibited'],
        ]);

        DB::transaction(function () use ($request, $documentRequest) {
            $actor = $request->user()->fresh();

            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize('process-document-requests');

            $item = DocumentRequest::query()
                ->lockForUpdate()
                ->findOrFail($documentRequest->id);

            if (
                ! array_key_exists($item->document_type, DocumentRequest::TYPES)
                || $item->status !== 'approved'
            ) {
                throw ValidationException::withMessages([
                    'preparation' =>
                        'Only an approved supported document request can be prepared here.',
                ]);
            }

            if ($item->prepared_at !== null) {
                throw ValidationException::withMessages([
                    'preparation' =>
                        'Preparation details are already saved. Refresh the page.',
                ]);
            }

            $this->lockEligibleResident($item, 'preparation');

            if (
                ! is_array($item->approved_snapshot)
                || empty($item->approved_snapshot['first_name'])
            ) {
                throw ValidationException::withMessages([
                    'preparation' => 'This request has no approved resident snapshot.',
                ]);
            }

            $captain = $this->designatedCaptain(true);

            if (! $captain) {
                throw ValidationException::withMessages([
                    'preparation' =>
                        'No valid designated Captain is configured. '
                        . 'Check BARANGAY_CAPTAIN_USER_ID and the Captain account.',
                ]);
            }

            $signatoryName = Str::squish($captain->name);

            if (mb_strlen($signatoryName) > 150) {
                throw ValidationException::withMessages([
                    'preparation' =>
                        'The Captain account name exceeds the 150-character limit.',
                ]);
            }

            $now = now();
            $localDate = $now->copy()->timezone('Asia/Manila');

            $documentCode = match ($item->document_type) {
                'barangay_clearance' => 'CLR',
                'certificate_of_indigency' => 'IND',
                'certificate_of_residency' => 'RES',
            };

            $controlNumber = sprintf(
                'MAS-%s-%s-%06d',
                $documentCode,
                $localDate->format('Y'),
                $item->id
            );

            $updated = DocumentRequest::query()
                ->whereKey($item->id)
                ->where('status', 'approved')
                ->whereNull('prepared_at')
                ->update([
                    'control_number' => $controlNumber,
                    'issuance_date' => $localDate->toDateString(),
                    'signatory_name' => $signatoryName,
                    'prepared_at' => $now,
                    'prepared_by' => $actor->id,
                ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'preparation' =>
                        'The request changed. Refresh the page before continuing.',
                ]);
            }

            DB::table('document_request_events')->insert([
                'document_request_id' => $item->id,
                'actor_id' => $actor->id,
                'from_status' => 'approved',
                'to_status' => 'approved',
                'note' =>
                    'Preparation details saved. Control number: '
                    . $controlNumber
                    . '. Designated Captain: '
                    . $signatoryName
                    . ' (user ID ' . $captain->id . ').',
                'occurred_at' => $now,
            ]);
        }, 3);

        return redirect()
            ->route('staff.documents.show', $documentRequest)
            ->with(
                'success',
                'Preparation details saved using the designated Captain’s name.'
            );
    }

    private function processingBlockReason(?Resident $resident): ?string
    {
        if (! $resident) {
            return 'The resident record is archived or unavailable. '
                . 'Review the registry record before continuing.';
        }

        if ($resident->isDeceased()) {
            return 'The resident has a recorded death dated '
                . $resident->date_of_death->format('M d, Y')
                . '. Approval, preparation, and release are blocked. '
                . 'Use the resident correction process if this information is incorrect.';
        }

        return null;
    }

    private function lockEligibleResident(
        DocumentRequest $item,
        string $field
    ): Resident {
        $resident = Resident::query()
            ->lockForUpdate()
            ->find($item->resident_id);

        $reason = $this->processingBlockReason($resident);

        if ($reason !== null) {
            throw ValidationException::withMessages([
                $field => $reason,
            ]);
        }

        return $resident;
    }

    private function profile(Resident $resident): array
    {
        return [
            'resident_number' => $resident->resident_number,
            'first_name' => $resident->first_name,
            'middle_name' => $resident->middle_name,
            'last_name' => $resident->last_name,
            'birth_date' => $resident->birth_date->format('Y-m-d'),
            'sex' => $resident->sex,
            'sitio' => $resident->sitio,
            'address' => $resident->address,
        ];
    }

    private function designatedCaptain(bool $lock = false): ?User
    {
        $captainId = filter_var(
            config('barangay.captain_user_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($captainId === false) {
            return null;
        }

        $query = User::query()
            ->whereKey($captainId)
            ->where('role', 'captain')
            ->where('account_status', 'approved');

        if ($lock) {
            $query->lockForUpdate();
        }

        $captain = $query->first();

        if (
            ! $captain
            || ! $captain->canAccessServices()
            || trim((string) $captain->name) === ''
        ) {
            return null;
        }

        return $captain;
    }
}