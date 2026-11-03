<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\HouseholdChange;
use App\Models\HouseholdMember;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HouseholdMembershipController extends Controller
{
    public function manage(Household $household): View
    {
        Gate::authorize('manage-residents');

        $household->load('head');

        // Keep all members for relationship review and historical visibility.
        $memberships = $household->memberships()
            ->with('resident')
            ->orderBy('id')
            ->get();

        return view('households.manage', [
            'household' => $household,
            'memberships' => $memberships,
            'relationships' => HouseholdMember::RELATIONSHIPS,
            'membershipFingerprint' => $this->fingerprint(
                $household,
                $memberships
            ),
        ]);
    }

    public function changeHead(
        Request $request,
        Household $household
    ): RedirectResponse {
        Gate::authorize('manage-residents');

        $request->merge([
            'reason' => trim((string) $request->input('reason', '')),
        ]);

        $data = $request->validate([
            'membership_fingerprint' => ['required', 'string', 'size:64'],
            'relationships' => ['required', 'array'],
            'relationships.*' => [
                'required',
                Rule::in(array_keys(HouseholdMember::RELATIONSHIPS)),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'details_confirmed' => ['accepted'],
        ]);

        DB::transaction(function () use ($request, $household, $data) {
            $actor = $request->user()->fresh();
            abort_unless($actor, 403);

            Gate::forUser($actor)->authorize('manage-residents');

            $current = Household::query()
                ->lockForUpdate()
                ->findOrFail($household->id);

            $memberships = $current->memberships()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->checkFingerprint(
                $current,
                $memberships,
                $data['membership_fingerprint']
            );

            $expectedIds = $memberships->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->sort()
                ->values()
                ->all();

            $submittedIds = collect(array_keys($data['relationships']))
                ->map(fn ($id) => (string) $id)
                ->sort()
                ->values()
                ->all();

            if ($expectedIds !== $submittedIds) {
                throw ValidationException::withMessages([
                    'relationships' =>
                        'Review the relationship of every current member.',
                ]);
            }

            $headIds = array_keys($data['relationships'], 'head', true);

            if (count($headIds) !== 1) {
                throw ValidationException::withMessages([
                    'relationships' =>
                        'Select exactly one member as Head of household.',
                ]);
            }

            $newHeadMembership = $memberships->firstWhere(
                'id',
                (int) $headIds[0]
            );

            if (! $newHeadMembership) {
                throw ValidationException::withMessages([
                    'relationships' =>
                        'Select a member of this household as the new head.',
                ]);
            }

            $newHead = Resident::query()
                ->lockForUpdate()
                ->find($newHeadMembership->resident_id);

            if (! $newHead || $newHead->isDeceased()) {
                throw ValidationException::withMessages([
                    'relationships' =>
                        'The new head must have an unarchived resident record '
                        . 'with no recorded death.',
                ]);
            }

            if ((int) $current->head_resident_id === (int) $newHead->id) {
                throw ValidationException::withMessages([
                    'relationships' =>
                        'Choose a different head for this operation.',
                ]);
            }

            if (
                Household::where('head_resident_id', $newHead->id)
                    ->where('id', '!=', $current->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'relationships' =>
                        'This resident is already the head of another household.',
                ]);
            }

            $before = $this->headSnapshot($current, $memberships);

            foreach ($memberships as $membership) {
                $membership->relationship_to_head =
                    $data['relationships'][$membership->id];

                $membership->save();
            }

            $current->head_resident_id = $newHead->id;
            $current->save();

            $after = $this->headSnapshot($current, $memberships);

            $this->recordChange(
                $current,
                $actor->id,
                'head_changed',
                $before,
                $after,
                $data['reason']
            );
        }, 3);

        return redirect()
            ->route('households.show', $household)
            ->with('success', 'Household head and member relationships updated.');
    }

    public function transfer(
        Request $request,
        Household $household
    ): RedirectResponse {
        Gate::authorize('manage-residents');

        $relationships = HouseholdMember::RELATIONSHIPS;
        unset($relationships['head']);

        $request->merge([
            'destination_number' => trim(
                (string) $request->input('destination_number', '')
            ),
            'reason' => trim((string) $request->input('reason', '')),
        ]);

        $data = $request->validate([
            'membership_fingerprint' => ['required', 'string', 'size:64'],
            'membership_id' => ['required', 'integer', 'min:1'],
            'destination_number' => ['required', 'string', 'max:40'],
            'relationship_to_head' => [
                'required',
                Rule::in(array_keys($relationships)),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'details_confirmed' => ['accepted'],
        ]);

        $destinationId = Household::where(
            'household_number',
            $data['destination_number']
        )->value('id');

        if (! $destinationId || (int) $destinationId === (int) $household->id) {
            throw ValidationException::withMessages([
                'destination_number' =>
                    'Enter the exact number of a different existing household.',
            ]);
        }

        DB::transaction(function () use (
            $request,
            $household,
            $destinationId,
            $data
        ) {
            $actor = $request->user()->fresh();
            abort_unless($actor, 403);

            Gate::forUser($actor)->authorize('manage-residents');

            $locked = Household::query()
                ->whereIn('id', [$household->id, $destinationId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $source = $locked->get($household->id);
            $destination = $locked->get($destinationId);

            abort_unless($source && $destination, 404);

            $memberships = $source->memberships()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->checkFingerprint(
                $source,
                $memberships,
                $data['membership_fingerprint']
            );

            $membership = $memberships->firstWhere(
                'id',
                (int) $data['membership_id']
            );

            if (! $membership) {
                throw ValidationException::withMessages([
                    'membership_id' =>
                        'This member no longer belongs to the source household.',
                ]);
            }

            if (
                (int) $membership->resident_id === (int) $source->head_resident_id
                || $membership->relationship_to_head === 'head'
            ) {
                throw ValidationException::withMessages([
                    'membership_id' =>
                        'Change the household head before transferring this resident.',
                ]);
            }

            // Use a consistent order when locking the resident records.
            $residents = Resident::query()
                ->whereIn('id', [
                    $membership->resident_id,
                    $destination->head_resident_id,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $resident = $residents->get($membership->resident_id);
            $destinationHead = $residents->get($destination->head_resident_id);

            if (! $resident || $resident->isDeceased()) {
                throw ValidationException::withMessages([
                    'membership_id' =>
                        'An archived or deceased resident cannot be transferred. '
                        . 'Their existing membership is retained.',
                ]);
            }

            if (! $destinationHead || $destinationHead->isDeceased()) {
                throw ValidationException::withMessages([
                    'destination_number' =>
                        'The destination household needs an eligible replacement '
                        . 'head before receiving members.',
                ]);
            }

            $residentLabel = $this->residentLabel($resident->id);

            $before = [
                'resident' => $residentLabel,
                'household_number' => $source->household_number,
                'relationship' => HouseholdMember::RELATIONSHIPS[
                    $membership->relationship_to_head
                ] ?? $membership->relationship_to_head,
            ];

            $membership->household_id = $destination->id;
            $membership->relationship_to_head = $data['relationship_to_head'];
            $membership->save();

            $after = [
                'resident' => $residentLabel,
                'household_number' => $destination->household_number,
                'relationship' => HouseholdMember::RELATIONSHIPS[
                    $data['relationship_to_head']
                ],
            ];

            $this->recordChange(
                $source,
                $actor->id,
                'member_transferred_out',
                $before,
                $after,
                $data['reason']
            );

            $this->recordChange(
                $destination,
                $actor->id,
                'member_transferred_in',
                $before,
                $after,
                $data['reason']
            );
        }, 3);

        return redirect()
            ->route('households.show', $household)
            ->with('success', 'Member transferred. Both households have a history entry.');
    }

    private function fingerprint(
        Household $household,
        Collection $memberships
    ): string {
        return hash('sha256', json_encode([
            'head_resident_id' => (int) $household->head_resident_id,
            'memberships' => $memberships->sortBy('id')
                ->map(fn ($membership) => [
                    'id' => (int) $membership->id,
                    'resident_id' => (int) $membership->resident_id,
                    'relationship' => $membership->relationship_to_head,
                ])
                ->values()
                ->all(),
        ]));
    }

    private function checkFingerprint(
        Household $household,
        Collection $memberships,
        string $fingerprint
    ): void {
        if (! hash_equals(
            $this->fingerprint($household, $memberships),
            $fingerprint
        )) {
            throw ValidationException::withMessages([
                'household' =>
                    'Household membership changed. Return to the household '
                    . 'page and reopen Manage members before continuing.',
            ]);
        }
    }

    private function residentLabel(int $residentId): string
    {
        $resident = Resident::withTrashed()->findOrFail($residentId);

        return trim(implode(' ', array_filter([
            $resident->first_name,
            $resident->middle_name,
            $resident->last_name,
        ]))) . ' [' . $resident->resident_number . ']';
    }

    private function headSnapshot(
        Household $household,
        Collection $memberships
    ): array {
        $snapshot = [
            'head' => $this->residentLabel($household->head_resident_id),
        ];

        foreach ($memberships as $membership) {
            $snapshot['member_' . $membership->resident_id] =
                $this->residentLabel($membership->resident_id)
                . ' — '
                . (HouseholdMember::RELATIONSHIPS[
                    $membership->relationship_to_head
                ] ?? $membership->relationship_to_head);
        }

        return $snapshot;
    }

    private function recordChange(
        Household $household,
        int $actorId,
        string $action,
        array $before,
        array $after,
        string $reason
    ): void {
        $change = new HouseholdChange();
        $change->household_id = $household->id;
        $change->actor_id = $actorId;
        $change->action = $action;
        $change->before_values = $before;
        $change->after_values = $after;
        $change->reason = $reason;
        $change->occurred_at = now();
        $change->save();
    }
}