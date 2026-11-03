<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResidentStatusController extends Controller
{
    private function authorizeOfficer(
        Request $request,
        ?string $requiredRole = null
    ): void {
        Gate::authorize('manage-residents');

        $user = $request->user()->fresh();

        abort_unless(
            $user
                && $user->canAccessServices()
                && in_array($user->role, ['secretary', 'captain'], true),
            403
        );

        if ($requiredRole !== null) {
            abort_unless($user->role === $requiredRole, 403);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'status' => $message,
        ]);
    }

    private function validateDeathDate(
        Resident $resident,
        string $date
    ): void {
        if (
            $date < $resident->birth_date->format('Y-m-d')
            || $date > now('Asia/Manila')->toDateString()
        ) {
            $this->fail(
                'The date of death must be on or after the birth date '
                . 'and cannot be after today.'
            );
        }
    }

    public function edit(Request $request, Resident $resident): View
    {
        $this->authorizeOfficer($request);

        $events = DB::table('resident_status_events as events')
            ->join('users', 'users.id', '=', 'events.actor_id')
            ->where('events.resident_id', $resident->id)
            ->select('events.*', 'users.name as actor_name')
            ->orderByDesc('events.id')
            ->paginate(10, ['*'], 'history_page')
            ->withQueryString();

        $corrections = DB::table('resident_status_corrections as corrections')
            ->join('users as requester', 'requester.id', '=', 'corrections.requested_by')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'corrections.reviewed_by')
            ->where('corrections.resident_id', $resident->id)
            ->select(
                'corrections.*',
                'requester.name as requester_name',
                'reviewer.name as reviewer_name'
            )
            ->orderByDesc('corrections.id')
            ->paginate(10, ['*'], 'corrections_page')
            ->withQueryString();

        $hasPendingCorrection = DB::table('resident_status_corrections')
            ->where('pending_resident_id', $resident->id)
            ->exists();

        return view('residents.status', compact(
            'resident',
            'events',
            'corrections',
            'hasPendingCorrection'
        ));
    }

    public function update(
        Request $request,
        Resident $resident
    ): RedirectResponse {
        $this->authorizeOfficer($request);

        $request->merge([
            'reason' => trim((string) $request->input('reason', '')),
        ]);

        $data = $request->validate([
            'status' => ['required', 'in:deceased'],
            'date_of_death' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . now('Asia/Manila')->toDateString(),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'status_version' => ['required', 'integer', 'min:0'],
            'confirmed' => ['accepted'],
        ]);

        DB::transaction(function () use ($request, $resident, $data) {
            $this->authorizeOfficer($request);

            $current = Resident::query()
                ->lockForUpdate()
                ->findOrFail($resident->id);

            if ($current->isDeceased()) {
                $this->fail(
                    'This deceased record is locked. '
                    . 'Submit a correction request for review.'
                );
            }

            if ((int) $current->status_version !== (int) $data['status_version']) {
                $this->fail('The status changed. Reload the page before saving.');
            }

            $this->validateDeathDate($current, $data['date_of_death']);

            $changed = Resident::query()
                ->whereKey($current->id)
                ->where('status_version', $data['status_version'])
                ->update([
                    'date_of_death' => $data['date_of_death'],
                    'status_version' => (int) $current->status_version + 1,
                    'updated_at' => now(),
                ]);

            if ($changed !== 1) {
                $this->fail('The record changed while saving. Reload and try again.');
            }

            DB::table('resident_status_events')->insert([
                'resident_id' => $current->id,
                'actor_id' => $request->user()->id,
                'previous_date_of_death' => null,
                'new_date_of_death' => $data['date_of_death'],
                'reason' => $data['reason'],
                'occurred_at' => now(),
            ]);
        }, 3);

        return redirect()
            ->route('residents.status.edit', $resident)
            ->with('success', 'Verified death recorded. The record is now locked.');
    }

    public function requestCorrection(
        Request $request,
        Resident $resident
    ): RedirectResponse {
        $this->authorizeOfficer($request, 'secretary');

        $request->merge([
            'correction_reason' => trim(
                (string) $request->input('correction_reason', '')
            ),
            'supporting_reference' => trim(
                (string) $request->input('supporting_reference', '')
            ),
        ]);

        $data = $request->validate([
            'correction_type' => ['required', 'in:correct_date,clear_death'],
            'proposed_date_of_death' => [
                'exclude_unless:correction_type,correct_date',
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . now('Asia/Manila')->toDateString(),
            ],
            'correction_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'supporting_reference' => ['required', 'string', 'max:255'],
            'status_version' => ['required', 'integer', 'min:0'],
            'correction_confirmed' => ['accepted'],
        ]);

        try {
            DB::transaction(function () use ($request, $resident, $data) {
                $this->authorizeOfficer($request, 'secretary');

                $current = Resident::query()
                    ->lockForUpdate()
                    ->findOrFail($resident->id);

                if (! $current->isDeceased()) {
                    $this->fail('This resident has no recorded death to correct.');
                }

                if ((int) $current->status_version !== (int) $data['status_version']) {
                    $this->fail('The record changed. Reload before requesting a correction.');
                }

                if (
                    DB::table('resident_status_corrections')
                        ->where('pending_resident_id', $current->id)
                        ->exists()
                ) {
                    $this->fail('This resident already has a pending correction request.');
                }

                $proposedDate = $data['correction_type'] === 'correct_date'
                    ? $data['proposed_date_of_death']
                    : null;

                $originalDate = $current->date_of_death->format('Y-m-d');

                if ($proposedDate !== null) {
                    $this->validateDeathDate($current, $proposedDate);
                }

                if ($proposedDate === $originalDate) {
                    $this->fail('The proposed date is the same as the recorded date.');
                }

                DB::table('resident_status_corrections')->insert([
                    'resident_id' => $current->id,
                    'pending_resident_id' => $current->id,
                    'requested_by' => $request->user()->id,
                    'resident_status_version' => $current->status_version,
                    'original_date_of_death' => $originalDate,
                    'proposed_date_of_death' => $proposedDate,
                    'reason' => $data['correction_reason'],
                    'supporting_reference' => $data['supporting_reference'],
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if (
                DB::table('resident_status_corrections')
                    ->where('pending_resident_id', $resident->id)
                    ->exists()
            ) {
                $this->fail('This resident already has a pending correction request.');
            }

            throw $exception;
        }

        return redirect()
            ->route('residents.status.edit', $resident)
            ->with(
                'success',
                'Correction submitted for Captain review. The resident record is unchanged.'
            );
    }

    public function reviewCorrection(
        Request $request,
        Resident $resident,
        string $correction
    ): RedirectResponse {
        $this->authorizeOfficer($request, 'captain');

        $request->merge([
            'review_note' => trim((string) $request->input('review_note', '')),
        ]);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_note' => ['required', 'string', 'min:10', 'max:1000'],
            'review_confirmed' => ['accepted'],
        ]);

        DB::transaction(function () use (
            $request,
            $resident,
            $correction,
            $data
        ) {
            $this->authorizeOfficer($request, 'captain');

            $current = Resident::query()
                ->lockForUpdate()
                ->findOrFail($resident->id);

            $item = DB::table('resident_status_corrections')
                ->where('id', $correction)
                ->where('resident_id', $current->id)
                ->lockForUpdate()
                ->first();

            abort_unless($item, 404);

            if ((int) $item->requested_by === (int) $request->user()->id) {
                $this->fail('You cannot review your own correction request.');
            }

            if ($item->status !== 'pending') {
                $this->fail('This correction request has already been reviewed.');
            }

            if ($data['decision'] === 'approved') {
                if (
                    (int) $current->status_version !== (int) $item->resident_status_version
                    || $current->date_of_death?->format('Y-m-d')
                        !== $item->original_date_of_death
                ) {
                    $this->fail(
                        'This request is based on an outdated record. '
                        . 'Reject it and ask the Secretary to submit a new request.'
                    );
                }

                if ($item->proposed_date_of_death !== null) {
                    $this->validateDeathDate(
                        $current,
                        $item->proposed_date_of_death
                    );
                }

                $changed = Resident::query()
                    ->whereKey($current->id)
                    ->where('status_version', $item->resident_status_version)
                    ->update([
                        'date_of_death' => $item->proposed_date_of_death,
                        'status_version' => (int) $current->status_version + 1,
                        'updated_at' => now(),
                    ]);

                if ($changed !== 1) {
                    $this->fail('The record changed during approval. Reload and review again.');
                }

                DB::table('resident_status_events')->insert([
                    'resident_id' => $current->id,
                    'actor_id' => $request->user()->id,
                    'previous_date_of_death' => $item->original_date_of_death,
                    'new_date_of_death' => $item->proposed_date_of_death,
                    'reason' =>
                        'Approved correction #' . $item->id
                        . '. Secretary reason: ' . $item->reason
                        . '. Supporting reference: ' . $item->supporting_reference
                        . '. Captain review: ' . $data['review_note'],
                    'occurred_at' => now(),
                ]);
            }

            $reviewed = DB::table('resident_status_corrections')
                ->where('id', $item->id)
                ->where('status', 'pending')
                ->update([
                    'status' => $data['decision'],
                    'pending_resident_id' => null,
                    'reviewed_by' => $request->user()->id,
                    'review_note' => $data['review_note'],
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($reviewed !== 1) {
                $this->fail('Another reviewer already processed this request.');
            }
        }, 3);

        return redirect()
            ->route('residents.status.edit', $resident)
            ->with(
                'success',
                $data['decision'] === 'approved'
                    ? 'Correction approved and applied. History has been retained.'
                    : 'Correction rejected. The resident record is unchanged.'
            );
    }
}