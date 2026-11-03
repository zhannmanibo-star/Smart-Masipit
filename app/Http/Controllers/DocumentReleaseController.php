<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\DocumentReadyForPickup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentReleaseController extends Controller
{
    public function update(
        Request $request,
        DocumentRequest $documentRequest
    ): RedirectResponse {
        Gate::authorize('process-document-requests');

        $request->merge([
            'pickup_instructions' => trim(
                (string) $request->input('pickup_instructions', '')
            ),
            'received_by' => Str::squish(
                (string) $request->input('received_by', '')
            ),
        ]);

        $data = $request->validate([
            'action' => ['required', 'in:ready,release'],

            'pickup_instructions' => [
                'required_if:action,ready',
                'nullable',
                'string',
                'min:10',
                'max:1000',
            ],

            'received_by' => [
                'required_if:action,release',
                'nullable',
                'string',
                'max:150',
            ],

            'recipient_type' => [
                'required_if:action,release',
                'nullable',
                'in:resident,authorized_representative',
            ],

            'confirmation' => ['accepted'],
        ]);

        DB::transaction(function () use (
            $request,
            $documentRequest,
            $data
        ) {
            $actor = $request->user()->fresh();

            abort_unless($actor, 403);

            Gate::forUser($actor)
                ->authorize('process-document-requests');

            $item = DocumentRequest::query()
                ->lockForUpdate()
                ->findOrFail($documentRequest->id);

            if (
                ! array_key_exists(
                    $item->document_type,
                    DocumentRequest::TYPES
                )
            ) {
                throw ValidationException::withMessages([
                    'release' => 'This document type is not supported.',
                ]);
            }

            abort_unless(
                in_array($item->source, ['online', 'walk_in'], true),
                403,
                'Unsupported request source.'
            );

            $isOwnOnlineRequest = $item->source === 'online'
                && (int) $item->requested_by === (int) $actor->id;

            $isOwnResidentRecord = $actor->resident_id !== null
                && (int) $actor->resident_id === (int) $item->resident_id;

            if ($isOwnOnlineRequest || $isOwnResidentRecord) {
                abort(403, 'You cannot release your own request.');
            }

            $resident = Resident::query()
                ->lockForUpdate()
                ->find($item->resident_id);

            if (! $resident) {
                throw ValidationException::withMessages([
                    'release' =>
                        'Processing is blocked because the resident record '
                        . 'is archived or unavailable. Review the registry record.',
                ]);
            }

            if ($resident->isDeceased()) {
                throw ValidationException::withMessages([
                    'release' =>
                        'This resident has a recorded death dated '
                        . $resident->date_of_death->format('M d, Y')
                        . '. The document cannot be marked ready or released. '
                        . 'Refer incorrect information to the resident correction process.',
                ]);
            }

            $now = now();

            if ($data['action'] === 'ready') {
                if (
                    $item->status !== 'approved'
                    || ! $item->prepared_at
                    || ! $item->control_number
                    || ! $item->issuance_date
                    || ! $item->signatory_name
                    || empty($item->approved_snapshot)
                ) {
                    throw ValidationException::withMessages([
                        'release' =>
                            'The request must be approved and its '
                            . 'preparation details saved first.',
                    ]);
                }

                $from = 'approved';
                $to = 'ready_for_release';

                $changes = [
                    'status' => $to,
                    'pickup_instructions' => $data['pickup_instructions'],
                    'ready_at' => $now,
                    'ready_by' => $actor->id,
                ];

                $note = 'Staff confirmed the completed document '
                    . 'has the required signature and is ready for pickup. '
                    . 'Pickup instructions: '
                    . $data['pickup_instructions'];
            } else {
                if ($item->status !== 'ready_for_release') {
                    throw ValidationException::withMessages([
                        'release' =>
                            'Only a request marked ready for release '
                            . 'can be released.',
                    ]);
                }

                $from = 'ready_for_release';
                $to = 'released';

                $changes = [
                    'status' => $to,
                    'released_at' => $now,
                    'released_by' => $actor->id,
                    'received_by' => $data['received_by'],
                    'recipient_type' => $data['recipient_type'],
                ];

                $recipientLabel =
                    $data['recipient_type'] === 'resident'
                        ? 'resident'
                        : 'authorized representative';

                $note = 'Document handed to '
                    . $data['received_by']
                    . ' (' . $recipientLabel . '). '
                    . 'Staff confirmed recipient identity and '
                    . 'authorization where applicable.';
            }

            $updated = DocumentRequest::query()
                ->whereKey($item->id)
                ->where('status', $from)
                ->update($changes);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'release' =>
                        'The request changed. Refresh the page '
                        . 'before trying again.',
                ]);
            }

            DB::table('document_request_events')->insert([
                'document_request_id' => $item->id,
                'actor_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'occurred_at' => $now,
            ]);
        }, 3);

        // The status and history have already been committed.
        $response = redirect()
            ->route('staff.documents.show', $documentRequest);

        if ($data['action'] === 'release') {
            return $response->with(
                'success',
                'Document release recorded successfully.'
            );
        }

        try {
            $queued = $this->queuePickupEmail($documentRequest->id);
        } catch (\Throwable $exception) {
            Log::error('Document pickup email could not be queued.', [
                'document_request_id' => $documentRequest->id,
                'exception_class' => $exception::class,
            ]);

            return $response
                ->with(
                    'success',
                    'Document marked ready for release.'
                )
                ->withErrors([
                    'notification' =>
                        'The document status was saved, but the pickup '
                        . 'email could not be queued. Do not repeat the '
                        . 'status change. Ask the system administrator '
                        . 'to check email delivery.',
                ]);
        }

        return $response->with(
            'success',
            $queued
                ? 'Document marked ready for release. '
                    . 'A pickup email notification has been queued.'
                : 'Document marked ready for release. '
                    . 'No pickup email was queued because the request '
                    . 'is no longer ready or no eligible resident '
                    . 'email account is available.'
        );
    }

    private function queuePickupEmail(int $documentRequestId): bool
    {
        $item = DocumentRequest::query()
            ->with('resident')
            ->findOrFail($documentRequestId);

        if (
            $item->status !== 'ready_for_release'
            || ! $item->resident
            || $item->resident->trashed()
            || $item->resident->isDeceased()
        ) {
            return false;
        }

        // Walk-in requests belong to the resident, not the encoder.
        $recipients = User::query()
            ->where('resident_id', $item->resident_id)
            ->where('role', 'resident')
            ->where('account_status', 'approved')
            ->whereNotNull('verified_by')
            ->whereNotNull('verified_at')
            ->when(
                $item->source === 'online',
                fn ($query) => $query->whereKey($item->requested_by)
            )
            ->limit(2)
            ->get();

        // Do not guess a recipient if the linkage is ambiguous.
        if ($recipients->count() !== 1) {
            return false;
        }

        $recipient = $recipients->first();

        if (
            ! $recipient->canAccessServices()
            || ! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)
        ) {
            return false;
        }

        $recipient->notify(
            new DocumentReadyForPickup($item->id)
        );

        return true;
    }
}