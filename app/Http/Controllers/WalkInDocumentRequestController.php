<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\Resident;
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

class WalkInDocumentRequestController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('process-document-requests');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'resident' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) $request->input('search', ''));

        $residents = Resident::query()
            ->whereNull('date_of_death')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('sitio', 'like', "%{$search}%")
                        ->orWhere('resident_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        $selectedResident = null;

        if ($request->filled('resident')) {
            $selectedResident = Resident::find($request->integer('resident'));

            if (! $selectedResident || $selectedResident->isDeceased()) {
                return redirect()
                    ->route('staff.documents.walk-in.create', ['search' => $search])
                    ->withErrors([
                        'resident_id' =>
                            'The selected resident is archived, unavailable, '
                            . 'or has a recorded death. A new walk-in request '
                            . 'cannot be created for this record.',
                    ]);
            }
        }

        return view('staff.documents.walk-in', [
            'residents' => $residents,
            'selectedResident' => $selectedResident,
            'search' => $search,
            'types' => DocumentRequest::TYPES,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('process-document-requests');

        $request->merge([
            'purpose' => trim((string) $request->input('purpose', '')),
        ]);

        $data = $request->validate([
            'resident_id' => ['required', 'integer', 'min:1'],
            'document_type' => [
                'required',
                Rule::in(array_keys(DocumentRequest::TYPES)),
            ],
            'purpose' => ['required', 'string', 'min:5', 'max:1000'],
            'submission_token' => ['required', 'uuid'],
            'identity_confirmed' => ['accepted'],
        ]);

        try {
            $documentRequest = DB::transaction(function () use ($request, $data) {
                $actor = User::query()
                    ->lockForUpdate()
                    ->findOrFail($request->user()->id);

                Gate::forUser($actor)->authorize('process-document-requests');

                $existing = DocumentRequest::query()
                    ->where('requested_by', $actor->id)
                    ->where('submission_token', $data['submission_token'])
                    ->first();

                if ($existing) {
                    if (
                        $existing->source !== 'walk_in'
                        || (int) $existing->resident_id !== (int) $data['resident_id']
                    ) {
                        throw ValidationException::withMessages([
                            'submission_token' =>
                                'This token is already used. Open a new walk-in form.',
                        ]);
                    }

                    return $existing;
                }

                $resident = Resident::query()
                    ->lockForUpdate()
                    ->find($data['resident_id']);

                if (! $resident) {
                    throw ValidationException::withMessages([
                        'resident_id' =>
                            'Select a resident with an unarchived registry record.',
                    ]);
                }

                if ($resident->isDeceased()) {
                    throw ValidationException::withMessages([
                        'resident_id' =>
                            'This resident has a recorded death. A new walk-in '
                            . 'request cannot be created. Refer any incorrect '
                            . 'status to the resident correction process.',
                    ]);
                }

                $item = new DocumentRequest();
                $item->reference_number = 'SM-' . Str::ulid();
                $item->resident_id = $resident->id;
                $item->requested_by = $actor->id;
                $item->submission_token = $data['submission_token'];
                $item->document_type = $data['document_type'];
                $item->purpose = $data['purpose'];
                $item->status = 'submitted';
                $item->source = 'walk_in';

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
                    'actor_id' => $actor->id,
                    'from_status' => null,
                    'to_status' => 'submitted',
                    'note' =>
                        'Walk-in request encoded. Staff confirmed the '
                        . 'resident identity and requested document details.',
                    'occurred_at' => now(),
                ]);

                return $item;
            }, 3);
        } catch (QueryException $exception) {
            $documentRequest = DocumentRequest::query()
                ->where('requested_by', $request->user()->id)
                ->where('resident_id', $data['resident_id'])
                ->where('submission_token', $data['submission_token'])
                ->where('source', 'walk_in')
                ->first();

            if (! $documentRequest) {
                throw $exception;
            }
        }

        return redirect()
            ->route('staff.documents.show', $documentRequest)
            ->with(
                'success',
                'Walk-in request recorded. Reference: '
                . $documentRequest->reference_number
            );
    }
}