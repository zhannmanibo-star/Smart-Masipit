<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResidentDocumentRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('request-documents');

        $documentRequests = DocumentRequest::query()
            ->where('resident_id', $request->user()->resident_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('documents.index', [
            'documentRequests' => $documentRequests,
            'types' => DocumentRequest::TYPES,
            'statuses' => DocumentRequest::STATUSES,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('request-documents');

        $resident = $request->user()->resident()->first();

        if (! $resident || $resident->isDeceased()) {
            return redirect()
                ->route('documents.index')
                ->withErrors([
                    'resident' =>
                        'A new document request cannot be submitted because '
                        . 'the linked resident record is unavailable or has a '
                        . 'recorded death. Contact the Barangay Secretary '
                        . 'if the information is incorrect.',
                ]);
        }

        return view('documents.create', [
            'resident' => $resident,
            'types' => DocumentRequest::TYPES,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('request-documents');

        $request->merge([
            'purpose' => trim((string) $request->input('purpose', '')),
        ]);

        $data = $request->validate([
            'document_type' => [
                'required',
                Rule::in(array_keys(DocumentRequest::TYPES)),
            ],
            'purpose' => ['required', 'string', 'min:5', 'max:1000'],
            'submission_token' => ['required', 'uuid'],
            'details_confirmed' => ['accepted'],
        ]);

        try {
            $documentRequest = DB::transaction(function () use ($request, $data) {
                $user = User::query()
                    ->lockForUpdate()
                    ->findOrFail($request->user()->id);

                Gate::forUser($user)->authorize('request-documents');

                $existing = DocumentRequest::query()
                    ->where('requested_by', $user->id)
                    ->where('submission_token', $data['submission_token'])
                    ->first();

                if ($existing) {
                    if (
                        $existing->source !== 'online'
                        || (int) $existing->resident_id !== (int) $user->resident_id
                    ) {
                        throw ValidationException::withMessages([
                            'submission_token' =>
                                'This token is already used. Open a new request form.',
                        ]);
                    }

                    // Return the existing request without creating another record.
                    return $existing;
                }

                $resident = $user->resident()
                    ->lockForUpdate()
                    ->first();

                if (! $resident || $resident->isDeceased()) {
                    throw ValidationException::withMessages([
                        'resident' =>
                            'This request cannot be submitted. The linked resident '
                            . 'record is unavailable or has a recorded death. '
                            . 'Contact the Barangay Secretary for review.',
                    ]);
                }

                $item = new DocumentRequest();
                $item->reference_number = 'SM-' . Str::ulid();
                $item->resident_id = $resident->id;
                $item->requested_by = $user->id;
                $item->submission_token = $data['submission_token'];
                $item->document_type = $data['document_type'];
                $item->purpose = $data['purpose'];
                $item->status = 'submitted';
                $item->source = 'online';

                $item->resident_snapshot = [
                    'resident_number' => $resident->resident_number,
                    'first_name' => $resident->first_name,
                    'middle_name' => $resident->middle_name,
                    'last_name' => $resident->last_name,
                    'birth_date' => $resident->birth_date->format('Y-m-d'),
                    'sex' => $resident->sex,
                    'sitio' => $resident->sitio,
                    'address' => $resident->address,
                ];

                $item->save();

                DB::table('document_request_events')->insert([
                    'document_request_id' => $item->id,
                    'actor_id' => $user->id,
                    'from_status' => null,
                    'to_status' => 'submitted',
                    'note' => 'Request submitted through the resident portal.',
                    'occurred_at' => now(),
                ]);

                return $item;
            }, 3);
        } catch (QueryException $exception) {
            $documentRequest = DocumentRequest::query()
                ->where('requested_by', $request->user()->id)
                ->where('resident_id', $request->user()->resident_id)
                ->where('submission_token', $data['submission_token'])
                ->where('source', 'online')
                ->first();

            if (! $documentRequest) {
                throw $exception;
            }
        }

        return redirect()
            ->route('documents.index')
            ->with(
                'success',
                'Request recorded. Reference: '
                . $documentRequest->reference_number
                . '. View your request list for its current status.'
            );
    }
}