<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Models\User;
use App\Support\CertificateDetails;
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
            ->when(
                $search !== '',
                fn ($query) => $query->where(
                    'reference_number',
                    'like',
                    "%{$search}%"
                )
            )
            ->when(
                $status !== '',
                fn ($query) => $query->where('status', $status)
            );

        if ($releasedOn !== '') {
            $day = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $releasedOn,
                'Asia/Manila'
            );

            $timezone = config('app.timezone', 'UTC');

            $query
                ->where(
                    'released_at',
                    '>=',
                    $day->setTimezone($timezone)
                )
                ->where(
                    'released_at',
                    '<',
                    $day->addDay()->setTimezone($timezone)
                );
        }

        return view('staff.documents.index', [
            'documentRequests' => $query
                ->orderBy('created_at')
                ->orderBy('id')
                ->paginate(10)
                ->withQueryString(),
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

        $resident = Resident::find($documentRequest->resident_id);

        $profile = $resident
            ? CertificateDetails::profile($resident)
            : null;

        return view('staff.documents.show', [
            'documentRequest' => $documentRequest,
            'currentProfile' => $profile,
            'residentFingerprint' => $this->reviewFingerprint(
                $documentRequest,
                $profile
            ),
            'processingBlockReason' => $this->processingBlockReason($resident),
            'types' => DocumentRequest::TYPES,
            'statuses' => DocumentRequest::STATUSES,
            'designatedCaptain' => $this->designatedCaptain(),
            'secretaries' => User::query()
                ->where('role', 'secretary')
                ->where('account_status', 'approved')
                ->orderBy('name')
                ->get(['id', 'name']),
            'events' => DB::table('document_request_events as events')
                ->leftJoin('users', 'users.id', '=', 'events.actor_id')
                ->where('events.document_request_id', $documentRequest->id)
                ->select('events.*', 'users.name as actor_name')
                ->orderBy('events.id')
                ->get(),
        ]);
    }

    public function updateStatus(
        Request $request,
        DocumentRequest $documentRequest
    ): RedirectResponse {
        Gate::authorize('process-document-requests');

        $note = $request->input('note');

        if (is_string($note)) {
            $note = trim($note);
        }

        $request->merge([
            'note' => $note === '' ? null : $note,
        ]);

        $data = $request->validate([
            'action' => [
                'required',
                'in:review,approve,reject,review_details',
            ],
            'note' => [
                'required_if:action,reject',
                'nullable',
                'string',
                'min:' . ($request->input('action') === 'reject' ? '10' : '1'),
                'max:2000',
            ],
            'resident_fingerprint' => [
                'required_if:action,approve,review_details',
                'nullable',
                'string',
                'size:64',
            ],
            'approved_details' => ['prohibited'],
            'approved_request_details' => ['prohibited'],
            'request_details' => ['prohibited'],
            'details' => ['prohibited'],
        ]);

        if ($data['action'] !== 'review') {
            Gate::authorize('approve-document-requests');

            $request->validate([
                'decision_confirmed' => ['accepted'],
            ]);
        }

        if (in_array($data['action'], ['approve', 'review_details'], true)) {
            $request->validate([
                'eligibility_confirmed' => ['accepted'],
            ]);
        }

        DB::transaction(function () use ($request, $documentRequest, $data) {
            $item = DocumentRequest::query()
                ->lockForUpdate()
                ->findOrFail($documentRequest->id);

            $actor = $request->user()->fresh();

            abort_unless($actor, 403);

            Gate::forUser($actor)->authorize('process-document-requests');

            $from = $item->status;
            $action = $data['action'];

            if ($action === 'review') {
                if ($from !== 'submitted') {
                    $this->fail(
                        'status',
                        'Only submitted requests can be moved to review.'
                    );
                }

                $item->status = 'under_review';
                $eventNote = 'Request moved to review.';
            } else {
                Gate::forUser($actor)->authorize('approve-document-requests');

                if ($action === 'review_details') {
                    if ($from !== 'approved' || $item->prepared_at !== null) {
                        $this->fail(
                            'status',
                            'Details can be verified only before preparation.'
                        );
                    }
                } elseif ($from !== 'under_review') {
                    $this->fail(
                        'status',
                        'Only requests under review can be approved or rejected.'
                    );
                }

                /*
                 * An authorized officer may approve a walk-in request
                 * they encoded for another resident.
                 *
                 * Personal requests still require another officer.
                 */
                $isOwnOnlineRequest = $item->source === 'online'
                    && (int) $item->requested_by === (int) $actor->id;

                $isOwnResidentRecord = $actor->resident_id !== null
                    && (int) $actor->resident_id === (int) $item->resident_id;

                if ($isOwnOnlineRequest || $isOwnResidentRecord) {
                    $this->fail(
                        'decision',
                        'Another authorized officer must decide a request '
                        . 'for your own resident record or your own online request.'
                    );
                }

                if ($action === 'reject') {
                    $item->status = 'rejected';
                    $item->rejection_reason = $data['note'];
                    $eventNote = $data['note'];
                } else {
                    abort_unless(
                        isset(CertificateDetails::CODES[$item->document_type]),
                        422
                    );

                    $resident = $this->lockEligibleResident($item, 'approval');

                    if ($item->source === 'online') {
                        $requester = User::query()
                            ->lockForUpdate()
                            ->find($item->requested_by);

                        if (
                            ! $requester
                            || ! $requester->canAccessServices()
                            || $requester->role !== 'resident'
                            || (int) $requester->resident_id !== (int) $resident->id
                        ) {
                            $this->fail(
                                'resident',
                                'The online requester is no longer eligible.'
                            );
                        }
                    } elseif ($item->source !== 'walk_in') {
                        $this->fail('source', 'Unsupported request source.');
                    }

                    $profile = CertificateDetails::profile($resident);

                    if (! hash_equals(
                        $this->reviewFingerprint($item, $profile),
                        $data['resident_fingerprint']
                    )) {
                        $this->fail(
                            'resident',
                            'The request or resident changed. Refresh and review again.'
                        );
                    }

                    // Always use submitted details from the database.
                    // The approval form cannot replace these fields.
                    $details = CertificateDetails::validate(
                        $item->document_type,
                        $item->request_details,
                        $profile['birth_date'],
                        'certificate_details'
                    );

                    if ($item->document_type === 'first_time_jobseeker_certification') {
                        $reference = $request->input('signed_oath_reference');

                        $request->merge([
                            'signed_oath_reference' => is_string($reference)
                                ? trim($reference)
                                : $reference,
                        ]);

                        $oath = $request->validate([
                            'signed_oath_confirmed' => ['accepted'],
                            'signed_oath_reference' => [
                                'required',
                                'string',
                                'min:3',
                                'max:150',
                            ],
                        ]);

                        $details['_signed_oath_reference'] =
                            $oath['signed_oath_reference'];

                        $details['_signed_oath_verified'] = true;
                    }

                    $details['_verified'] = true;
                    $details['_verified_by'] = $actor->id;
                    $details['_verified_at'] = now()->toIso8601String();

                    $item->approved_request_details = $details;
                    $item->approved_snapshot = $profile;
                    $item->status = 'approved';
                    $item->rejection_reason = null;

                    $eventNote = $action === 'review_details'
                        ? 'Submitted certificate details verified before preparation.'
                        : 'Request approved. Submitted details and eligibility verified.';

                    if (! empty($data['note'])) {
                        $eventNote .= ' Officer note: ' . $data['note'];
                    }
                }
            }

            $item->save();

            $this->recordEvent(
                $item,
                $actor->id,
                $from,
                $eventNote
            );
        }, 3);

        return redirect()
            ->route('staff.documents.show', $documentRequest)
            ->with('success', 'Request review saved successfully.');
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
            'approved_details' => ['prohibited'],
            'approved_request_details' => ['prohibited'],
        ]);

        DB::transaction(function () use ($request, $documentRequest) {
            $actor = $request->user()->fresh();

            abort_unless($actor, 403);

            Gate::forUser($actor)->authorize('process-document-requests');

            $item = DocumentRequest::query()
                ->lockForUpdate()
                ->findOrFail($documentRequest->id);

            if (
                ! isset(CertificateDetails::CODES[$item->document_type])
                || $item->status !== 'approved'
                || $item->prepared_at !== null
            ) {
                $this->fail(
                    'preparation',
                    'Only an approved, unprepared supported request can be prepared.'
                );
            }

            $this->lockEligibleResident($item, 'preparation');

            $profile = $item->approved_snapshot;
            $details = $item->approved_request_details;

            if (
                ! is_array($profile)
                || empty($profile['birth_date'])
                || ! is_array($details)
                || ($details['_verified'] ?? false) !== true
            ) {
                $this->fail(
                    'preparation',
                    'Complete certificate verification before preparation.'
                );
            }

            CertificateDetails::validate(
                $item->document_type,
                array_intersect_key(
                    $details,
                    array_flip(
                        CertificateDetails::TYPE_FIELDS[$item->document_type]
                    )
                ),
                $profile['birth_date'],
                'certificate_details'
            );

            if ($item->document_type === 'first_time_jobseeker_certification') {
                if (
                    ($details['_signed_oath_verified'] ?? false) !== true
                    || empty($details['_signed_oath_reference'])
                ) {
                    $this->fail(
                        'preparation',
                        'The signed oath must be verified first.'
                    );
                }

                // The resident row is locked to serialize preparation.
                $previous = DocumentRequest::query()
                    ->where('resident_id', $item->resident_id)
                    ->where(
                        'document_type',
                        'first_time_jobseeker_certification'
                    )
                    ->where('id', '<>', $item->id)
                    ->whereNotNull('prepared_at')
                    ->whereIn('status', [
                        'approved',
                        'ready_for_release',
                        'released',
                    ])
                    ->exists();

                if ($previous) {
                    $this->fail(
                        'preparation',
                        'A prepared or released First-Time Jobseeker Certification '
                        . 'already exists for this resident. Review that record.'
                    );
                }

                $input = $request->validate([
                    'secretary_user_id' => ['required', 'integer', 'min:1'],
                    'witness_confirmed' => ['accepted'],
                ]);

                $secretary = User::query()
                    ->lockForUpdate()
                    ->whereKey($input['secretary_user_id'])
                    ->where('role', 'secretary')
                    ->where('account_status', 'approved')
                    ->first();

                if (! $secretary || ! $secretary->canAccessServices()) {
                    $this->fail(
                        'secretary_user_id',
                        'Select an eligible Secretary.'
                    );
                }

                $details['_witness_name'] = Str::squish($secretary->name);
                $details['_witness_user_id'] = $secretary->id;
                $details['_witness_position'] = 'Barangay Secretary';
            }

            if ($item->document_type === 'oath_of_undertaking') {
                $name = $request->input('witness_name');

                $request->merge([
                    'witness_name' => is_string($name)
                        ? Str::squish($name)
                        : $name,
                ]);

                $input = $request->validate([
                    'witness_name' => [
                        'required',
                        'string',
                        'min:2',
                        'max:150',
                    ],
                    'witness_confirmed' => ['accepted'],
                ]);

                $details['_witness_name'] = $input['witness_name'];
                $details['_witness_position'] = 'SB Member';
            }

            if (mb_strlen((string) ($details['_witness_name'] ?? '')) > 150) {
                $this->fail(
                    'preparation',
                    'The witness name must not exceed 150 characters.'
                );
            }

            // Select the saved certificate signatory.
            // The Captain does not need to be logged in.
            $captain = $this->designatedCaptain(true);

            if (! $captain) {
                $this->fail(
                    'preparation',
                    'No valid designated Captain is configured. '
                    . 'Check BARANGAY_CAPTAIN_USER_ID.'
                );
            }

            $name = Str::squish($captain->name);

            if (mb_strlen($name) > 150) {
                $this->fail(
                    'preparation',
                    'The Captain name exceeds 150 characters.'
                );
            }

            $now = now();
            $date = $now->copy()->timezone('Asia/Manila');

            if ($item->document_type === 'first_time_jobseeker_certification') {
                $details['_valid_until'] = $date
                    ->copy()
                    ->addYearNoOverflow()
                    ->toDateString();
            }

            $details['_prepared_by'] = $actor->id;
            $details['_prepared_at'] = $now->toIso8601String();

            $item->approved_request_details = $details;
            $item->control_number = sprintf(
                'MAS-%s-%s-%06d',
                CertificateDetails::CODES[$item->document_type],
                $date->format('Y'),
                $item->id
            );
            $item->issuance_date = $date->toDateString();
            $item->signatory_name = $name;
            $item->prepared_at = $now;
            $item->prepared_by = $actor->id;
            $item->save();

            $this->recordEvent(
                $item,
                $actor->id,
                'approved',
                'Preparation saved. Control: ' . $item->control_number
                . '. Captain: ' . $name
                . (
                    isset($details['_witness_name'])
                        ? '. Witness: ' . $details['_witness_name']
                        : ''
                )
                . '. Printout remains DRAFT.'
            );
        }, 3);

        return redirect()
            ->route('staff.documents.show', $documentRequest)
            ->with('success', 'Certificate preparation details saved.');
    }

    public function previewResidency(DocumentRequest $documentRequest)
    {
        Gate::authorize('process-document-requests');

        abort_unless(
            isset(CertificateDetails::CODES[$documentRequest->document_type]),
            404
        );

        abort_unless(
            in_array($documentRequest->status, [
                'approved',
                'ready_for_release',
                'released',
            ], true),
            403,
            'Approval is required before preview.'
        );

        if ($documentRequest->status !== 'released') {
            $reason = $this->processingBlockReason(
                Resident::find($documentRequest->resident_id)
            );

            if ($reason !== null) {
                return redirect()
                    ->route('staff.documents.show', $documentRequest)
                    ->withErrors(['preview' => $reason]);
            }
        }

        $profile = $documentRequest->approved_snapshot;

        abort_unless(
            is_array($profile) && ! empty($profile['first_name']),
            409,
            'No approved resident snapshot is available.'
        );

        $fullName = implode(' ', array_filter([
            $profile['first_name'] ?? '',
            $profile['middle_name'] ?? '',
            $profile['last_name'] ?? '',
        ], fn ($value) => trim((string) $value) !== ''));

        $fullAddress = implode(', ', array_filter([
            $profile['address'] ?? '',
            ! empty($profile['sitio'])
                ? 'Sitio ' . $profile['sitio']
                : '',
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

    private function reviewFingerprint(
        DocumentRequest $item,
        ?array $profile
    ): string {
        return hash('sha256', json_encode([
            'resident' => $profile,
            'document_type' => $item->document_type,
            'purpose' => $item->purpose,
            'request_details' => $item->request_details,
            'approved_request_details' => $item->approved_request_details,
            'status' => $item->status,
            'prepared_at' => $item->prepared_at?->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    private function processingBlockReason(?Resident $resident): ?string
    {
        if (! $resident) {
            return 'The resident record is archived or unavailable.';
        }

        if ($resident->isDeceased()) {
            return 'The resident has a recorded death dated '
                . $resident->date_of_death->format('M d, Y')
                . '. Approval, preparation, and release are blocked.';
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
            $this->fail($field, $reason);
        }

        return $resident;
    }

    private function designatedCaptain(bool $lock = false): ?User
    {
        $id = filter_var(
            config('barangay.captain_user_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            return null;
        }

        $query = User::query()
            ->whereKey($id)
            ->where('role', 'captain')
            ->where('account_status', 'approved');

        if ($lock) {
            $query->lockForUpdate();
        }

        $captain = $query->first();

        return $captain
            && $captain->canAccessServices()
            && trim((string) $captain->name) !== ''
                ? $captain
                : null;
    }

    private function recordEvent(
        DocumentRequest $item,
        int $actorId,
        ?string $from,
        string $note
    ): void {
        DB::table('document_request_events')->insert([
            'document_request_id' => $item->id,
            'actor_id' => $actorId,
            'from_status' => $from,
            'to_status' => $item->status,
            'note' => $note,
            'occurred_at' => now(),
        ]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }
}