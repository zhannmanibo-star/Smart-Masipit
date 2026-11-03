<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\HouseholdChange;
use App\Models\HouseholdMember;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-residents');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) $request->input('search', ''));

        $households = Household::query()
            ->with('head')
            ->withCount('activeMemberships')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('household_number', 'like', "%{$search}%")
                        ->orWhere('sitio', 'like', "%{$search}%")
                        ->orWhereHas('head', function ($query) use ($search) {
                            $query->where(function ($query) use ($search) {
                                $query->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                        });
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('households.index', compact('households', 'search'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('manage-residents');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) $request->input('search', ''));

        $residents = $this->availableResidents($search)
            ->paginate(10)
            ->withQueryString();

        return view('households.create', compact('residents', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-residents');

        $request->merge([
            'sitio' => Str::squish((string) $request->input('sitio', '')),
            'address' => Str::squish((string) $request->input('address', '')),
        ]);

        $data = $request->validate([
            'resident_id' => ['required', 'integer', 'min:1'],
            'sitio' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'details_confirmed' => ['accepted'],
        ]);

        try {
            $household = DB::transaction(function () use ($request, $data) {
                $actor = $request->user()->fresh();

                abort_unless($actor, 403);
                Gate::forUser($actor)->authorize('manage-residents');

                $head = $this->lockAvailableResident($data['resident_id']);

                $household = new Household();
                $household->household_number = 'HH-' . Str::ulid();
                $household->head_resident_id = $head->id;
                $household->sitio = $data['sitio'];
                $household->address = $data['address'] ?: null;
                $household->created_by = $actor->id;
                $household->save();

                $membership = new HouseholdMember();
                $membership->household_id = $household->id;
                $membership->resident_id = $head->id;
                $membership->relationship_to_head = 'head';
                $membership->added_by = $actor->id;
                $membership->save();

                return $household;
            }, 3);
        } catch (QueryException $exception) {
            $this->handleAssignmentConflict($data['resident_id'], $exception);
        }

        return redirect()
            ->route('households.show', $household)
            ->with('success', 'Household created. The head is included as a member.');
    }

    public function show(Request $request, Household $household): View
    {
        Gate::authorize('manage-residents');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) $request->input('search', ''));

        $household->load('head')->loadCount('activeMemberships');

        // Keep all existing memberships visible, including deceased residents.
        $memberships = $household->memberships()
            ->with('resident')
            ->orderBy('id')
            ->paginate(20, ['*'], 'members_page')
            ->withQueryString();

        $residents = $this->availableResidents($search)
            ->paginate(10)
            ->withQueryString();

        $relationships = HouseholdMember::RELATIONSHIPS;
        unset($relationships['head']);

        return view('households.show', compact(
            'household',
            'memberships',
            'residents',
            'relationships',
            'search'
        ));
    }

    public function addMember(
        Request $request,
        Household $household
    ): RedirectResponse {
        Gate::authorize('manage-residents');

        $relationships = HouseholdMember::RELATIONSHIPS;
        unset($relationships['head']);

        $data = $request->validate([
            'resident_id' => ['required', 'integer', 'min:1'],
            'relationship_to_head' => [
                'required',
                Rule::in(array_keys($relationships)),
            ],
            'details_confirmed' => ['accepted'],
        ]);

        try {
            DB::transaction(function () use ($request, $household, $data) {
                $actor = $request->user()->fresh();

                abort_unless($actor, 403);
                Gate::forUser($actor)->authorize('manage-residents');

                $currentHousehold = Household::query()
                    ->lockForUpdate()
                    ->findOrFail($household->id);

                $head = Resident::query()
                    ->lockForUpdate()
                    ->find($currentHousehold->head_resident_id);

                if (! $head || $head->isDeceased()) {
                    throw ValidationException::withMessages([
                        'household' =>
                            'The household head is archived, unavailable, or deceased. '
                            . 'Assign an eligible replacement head before adding members.',
                    ]);
                }

                $resident = $this->lockAvailableResident($data['resident_id']);

                $membership = new HouseholdMember();
                $membership->household_id = $currentHousehold->id;
                $membership->resident_id = $resident->id;
                $membership->relationship_to_head = $data['relationship_to_head'];
                $membership->added_by = $actor->id;
                $membership->save();
            }, 3);
        } catch (QueryException $exception) {
            $this->handleAssignmentConflict($data['resident_id'], $exception);
        }

        return redirect()
            ->route('households.show', $household)
            ->with('success', 'Resident added to the household.');
    }

    public function edit(Household $household): View
    {
        Gate::authorize('manage-residents');

        return view('households.edit', compact('household'));
    }

    public function update(
        Request $request,
        Household $household
    ): RedirectResponse {
        Gate::authorize('manage-residents');

        $request->merge([
            'sitio' => Str::squish((string) $request->input('sitio', '')),
            'address' => Str::squish((string) $request->input('address', '')),
            'reason' => trim((string) $request->input('reason', '')),
        ]);

        $data = $request->validate([
            'sitio' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'location_version' => ['required', 'integer', 'min:0'],
            'details_confirmed' => ['accepted'],
        ]);

        DB::transaction(function () use ($request, $household, $data) {
            $actor = $request->user()->fresh();

            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize('manage-residents');

            $current = Household::query()
                ->lockForUpdate()
                ->findOrFail($household->id);

            if ((int) $current->location_version !== (int) $data['location_version']) {
                throw ValidationException::withMessages([
                    'household' =>
                        'Another staff member changed this household location. '
                        . 'Reopen Edit location to review the latest details.',
                ]);
            }

            $before = [
                'sitio' => $current->sitio,
                'address' => $current->address,
            ];

            $after = [
                'sitio' => $data['sitio'],
                'address' => $data['address'] ?: null,
            ];

            if ($before === $after) {
                throw ValidationException::withMessages([
                    'household' => 'No location changes were made.',
                ]);
            }

            $updated = Household::query()
                ->whereKey($current->id)
                ->where('location_version', $data['location_version'])
                ->update([
                    'sitio' => $after['sitio'],
                    'address' => $after['address'],
                    'location_version' => (int) $current->location_version + 1,
                ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'household' =>
                        'The household changed while saving. '
                        . 'Reopen Edit location before continuing.',
                ]);
            }

            $change = new HouseholdChange();
            $change->household_id = $current->id;
            $change->actor_id = $actor->id;
            $change->action = 'location_updated';
            $change->before_values = $before;
            $change->after_values = $after;
            $change->reason = $data['reason'];
            $change->occurred_at = now();
            $change->save();
        }, 3);

        return redirect()
            ->route('households.show', $household)
            ->with('success', 'Household location updated and recorded in history.');
    }

    public function history(Household $household): View
    {
        Gate::authorize('manage-residents');

        $changes = HouseholdChange::query()
            ->where('household_id', $household->id)
            ->with('actor')
            ->orderByDesc('id')
            ->paginate(10);

        return view('households.history', compact('household', 'changes'));
    }

    private function availableResidents(string $search): Builder
    {
        return Resident::query()
            ->whereNull('date_of_death')
            ->whereDoesntHave('householdMembership')
            ->whereDoesntHave('headedHousehold')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('resident_number', 'like', "%{$search}%")
                        ->orWhere('sitio', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    private function lockAvailableResident(int $residentId): Resident
    {
        $resident = Resident::query()
            ->lockForUpdate()
            ->find($residentId);

        if (! $resident) {
            throw ValidationException::withMessages([
                'resident_id' => 'Select a resident with an unarchived registry record.',
            ]);
        }

        if ($resident->isDeceased()) {
            throw ValidationException::withMessages([
                'resident_id' =>
                    'A deceased resident cannot be assigned as a new '
                    . 'household head or member.',
            ]);
        }

        if (
            $resident->householdMembership()->exists()
            || $resident->headedHousehold()->exists()
        ) {
            throw ValidationException::withMessages([
                'resident_id' =>
                    'This resident already belongs to a household. '
                    . 'Refresh the page and review the existing assignment.',
            ]);
        }

        return $resident;
    }

    private function handleAssignmentConflict(
        int $residentId,
        QueryException $exception
    ): never {
        if (
            HouseholdMember::where('resident_id', $residentId)->exists()
            || Household::where('head_resident_id', $residentId)->exists()
        ) {
            throw ValidationException::withMessages([
                'resident_id' =>
                    'This resident was already assigned to a household. '
                    . 'Refresh the page before continuing.',
            ]);
        }

        throw $exception;
    }
}