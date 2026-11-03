<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentClosureController extends Controller
{
    private const CLOSABLE_STATUSES = [
        'submitted',
        'under_review',
        'approved',
        'ready_for_release',
    ];

    public function update(
        Request $request,
        DocumentRequest $documentRequest
    ): RedirectResponse {
        Gate::authorize('process-document-requests');
        Gate::authorize('approve-document-requests');

        $request->merge([
            'closure_reason' => trim(
                (string) $request->input('closure_reason', '')
            ),
        ]);

        $data = $request->validate([
            'expected_status' => [
                'required',
                Rule::in(self::CLOSABLE_STATUSES),
            ],
            'closure_reason' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            'closure_confirmed' => ['accepted'],
        ]);

        DB::transaction(function () use ($request, $documentRequest, $data) {
            $actor = $request->user()->fresh();

            abort_unless($actor, 403);

            Gate::forUser($actor)->authorize('process-document-requests');
            Gate::forUser($actor)->authorize('approve-document-requests');

            $item = DocumentRequest::query()
                ->lockForUpdate()
                ->findOrFail($documentRequest->id);

            $isOwnSubmission = (int) $item->requested_by === (int) $actor->id;

            $isOwnResidentRecord = $actor->resident_id !== null
                && (int) $actor->resident_id === (int) $item->resident_id;

            if ($isOwnSubmission || $isOwnResidentRecord) {
                $this->fail(
                    'Another authorized approving officer must close a request '
                    . 'you submitted or encoded, or a request for your own resident record.'
                );
            }

            if (
                ! in_array($item->status, self::CLOSABLE_STATUSES, true)
                || $item->released_at !== null
                || $item->closed_at !== null
            ) {
                $this->fail(
                    'This request cannot be closed. Released, rejected, '
                    . 'and already closed requests are not eligible.'
                );
            }

            if ($item->status !== $data['expected_status']) {
                $this->fail(
                    'The request status changed after this page was opened. '
                    . 'Refresh and review it before closing.'
                );
            }

            // Include archived records to identify the actual blocking reason.
            $resident = Resident::withTrashed()
                ->lockForUpdate()
                ->find($item->resident_id);

            if (! $resident) {
                $blockingReason = 'Resident record unavailable.';
            } elseif ($resident->trashed()) {
                $blockingReason = 'Resident record archived.';
            } elseif ($resident->isDeceased()) {
                $blockingReason = 'Recorded death dated '
                    . $resident->date_of_death->format('Y-m-d') . '.';
            } else {
                $this->fail(
                    'The resident record is currently eligible. '
                    . 'This action is only for requests blocked by an archived, '
                    . 'unavailable, or deceased resident record.'
                );
            }

            $fromStatus = $item->status;
            $now = now();

            $updated = DocumentRequest::query()
                ->whereKey($item->id)
                ->where('status', $fromStatus)
                ->whereNull('released_at')
                ->whereNull('closed_at')
                ->update([
                    'status' => 'closed_not_released',
                    'closure_reason' => $data['closure_reason'],
                    'closed_at' => $now,
                    'closed_by' => $actor->id,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                $this->fail(
                    'Another staff member changed this request. '
                    . 'Refresh before continuing.'
                );
            }

            DB::table('document_request_events')->insert([
                'document_request_id' => $item->id,
                'actor_id' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => 'closed_not_released',
                'note' =>
                    'Closed without release. '
                    . $blockingReason
                    . ' Reason: ' . $data['closure_reason'],
                'occurred_at' => $now,
            ]);
        }, 3);

        return redirect()
            ->route('staff.documents.show', $documentRequest)
            ->with(
                'success',
                'Request closed without release. Saved details and history are retained.'
            );
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'closure' => $message,
        ]);
    }
}