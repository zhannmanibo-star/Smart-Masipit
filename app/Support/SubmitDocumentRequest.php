<?php

namespace App\Support;

use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubmitDocumentRequest
{
    public static function handle(Request $request, string $source): DocumentRequest
    {
        abort_unless(in_array($source, ['online', 'walk_in'], true), 400);

        $walkIn = $source === 'walk_in';
        $permission = $walkIn
            ? 'process-document-requests'
            : 'request-documents';

        Gate::authorize($permission);

        $purpose = $request->input('purpose');

        $request->merge([
            'purpose' => is_string($purpose) ? trim($purpose) : $purpose,
        ]);

        $rules = [
            'document_type' => [
                'required',
                'string',
                Rule::in(array_keys(DocumentRequest::TYPES)),
            ],
            'purpose' => ['required', 'string', 'min:5', 'max:1000'],
            'submission_token' => ['required', 'uuid'],
            'details' => ['nullable', 'array'],
            'approved_request_details' => ['prohibited'],
            'approved_details' => ['prohibited'],
            $walkIn ? 'identity_confirmed' : 'details_confirmed' => ['accepted'],
        ];

        if ($walkIn) {
            $rules['resident_id'] = ['required', 'integer', 'min:1'];
        }

        $data = $request->validate($rules);

        try {
            return DB::transaction(function () use (
                $request,
                $source,
                $permission,
                $walkIn,
                $data
            ) {
                $actor = User::query()
                    ->lockForUpdate()
                    ->findOrFail($request->user()->id);

                Gate::forUser($actor)->authorize($permission);

                $residentId = $walkIn
                    ? (int) $data['resident_id']
                    : (int) $actor->resident_id;

                $existing = DocumentRequest::query()
                    ->where('requested_by', $actor->id)
                    ->where('submission_token', $data['submission_token'])
                    ->first();

                if ($existing) {
                    if (
                        $existing->source !== $source
                        || (int) $existing->resident_id !== $residentId
                    ) {
                        throw ValidationException::withMessages([
                            'submission_token' =>
                                'This token is already used. Open a new request form.',
                        ]);
                    }

                    return $existing;
                }

                $resident = Resident::query()
                    ->lockForUpdate()
                    ->find($residentId);

                if (! $resident || $resident->isDeceased()) {
                    throw ValidationException::withMessages([
                        'resident' =>
                            'The resident is archived, unavailable, or has a '
                            . 'recorded death. A new request cannot be submitted.',
                    ]);
                }

                $details = CertificateDetails::validate(
                    $data['document_type'],
                    $data['details'] ?? [],
                    $resident->birth_date->format('Y-m-d')
                );

                $item = new DocumentRequest();
                $item->reference_number = 'SM-' . Str::ulid();
                $item->resident_id = $resident->id;
                $item->requested_by = $actor->id;
                $item->submission_token = $data['submission_token'];
                $item->document_type = $data['document_type'];
                $item->purpose = $data['purpose'];
                $item->source = $source;
                $item->status = 'submitted';
                $item->request_details = $details;
                $item->approved_request_details = null;
                $item->resident_snapshot = CertificateDetails::profile($resident);
                $item->save();

                DB::table('document_request_events')->insert([
                    'document_request_id' => $item->id,
                    'actor_id' => $actor->id,
                    'from_status' => null,
                    'to_status' => 'submitted',
                    'note' => $walkIn
                        ? 'Walk-in request encoded. Identity and submitted details confirmed; certificate eligibility awaits review.'
                        : 'Online request submitted. Certificate details and eligibility await barangay review.',
                    'occurred_at' => now(),
                ]);

                return $item;
            }, 3);
        } catch (QueryException $exception) {
            $state = (string) ($exception->errorInfo[0] ?? '');
            $code = (int) ($exception->errorInfo[1] ?? 0);

            $duplicate = $state === '23505'
                || ($state === '23000' && in_array($code, [1062, 19, 2067], true));

            if (! $duplicate) {
                throw $exception;
            }

            $residentId = $walkIn
                ? $data['resident_id']
                : $request->user()->fresh()?->resident_id;

            $existing = DocumentRequest::query()
                ->where('requested_by', $request->user()->id)
                ->where('resident_id', $residentId)
                ->where('submission_token', $data['submission_token'])
                ->where('source', $source)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            return $existing;
        }
    }
}