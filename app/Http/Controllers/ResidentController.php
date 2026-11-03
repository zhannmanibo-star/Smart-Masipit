<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;



class ResidentController extends Controller
{
    public function deleted(Request $request): View
{
    Gate::authorize('access-admin');

    $request->validate([
        'search' => ['nullable', 'string', 'max:100'],
    ]);

    $search = trim((string) $request->input('search', ''));

    $residents = Resident::onlyTrashed()
        ->when($search !== '', function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('resident_number', 'like', "%{$search}%");
            });
        })
        ->orderByDesc('deleted_at')
        ->orderByDesc('id')
        ->paginate(10)
        ->withQueryString();

    return view('residents.deleted', compact('residents', 'search'));
}

public function restore(string $id): RedirectResponse
{
    Gate::authorize('access-admin');

    $resident = Resident::onlyTrashed()->findOrFail($id);

    $normalize = fn (string $value): string =>
        Str::lower(Str::squish($value));

    $candidates = Resident::query()
        ->whereDate(
            'birth_date',
            $resident->birth_date->format('Y-m-d')
        )
        ->get([
            'id',
            'resident_number',
            'first_name',
            'last_name',
        ]);

    $match = $candidates->first(function ($candidate) use (
        $resident,
        $normalize
    ) {
        return $normalize($candidate->first_name)
                === $normalize($resident->first_name)
            && $normalize($candidate->last_name)
                === $normalize($resident->last_name);
    });

    if ($match) {
        return redirect()
            ->route('residents.deleted')
            ->withErrors([
                'restore' =>
                    'Restoration paused. An active resident has the same '
                    . 'first name, last name, and birth date. '
                    . 'Review the matching record before proceeding.',
            ])
            ->with('restore_match_id', $match->id)
            ->with('restore_match_reference', $match->resident_number);
    }

    $resident->restore();

    return redirect()
        ->route('residents.deleted')
        ->with(
            'success',
            'Resident restored successfully. The record is now '
            . 'available in the active registry.'
        );
}


  public function index(Request $request): View
{
    $request->validate([
        'search' => ['nullable', 'string', 'max:100'],
        'status' => ['nullable', 'in:no_recorded_death,deceased'],
    ]);

    $search = trim((string) $request->input('search', ''));
    $status = (string) $request->input('status', '');

    $residents = Resident::query()
        ->when($status === 'deceased', function ($query) {
            $query->deceased();
        })
        ->when($status === 'no_recorded_death', function ($query) {
            $query->withoutRecordedDeath();
        })
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

    return view(
        'residents.index',
        compact('residents', 'search', 'status')
    );
}

    public function create(): View
{
    return view('residents.create');
}

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedProfile($request);

        $this->checkDuplicate($request, $data);

        $resident = new Resident($data);
        $resident->resident_number = (string) Str::uuid();
        $resident->save();

        return redirect()
            ->route('residents.index')
            ->with('success', 'Resident added successfully.');
    }

    public function edit(Resident $resident): View
    {
        return view('residents.edit', compact('resident'));
    }

   public function update(
    Request $request,
    Resident $resident
): RedirectResponse {
    $data = $this->validatedProfile($request);

    DB::transaction(function () use ($request, $resident, $data) {
        $current = Resident::query()
            ->lockForUpdate()
            ->findOrFail($resident->id);

        if (
            $current->date_of_death !== null
            && $data['birth_date'] > $current->date_of_death->format('Y-m-d')
        ) {
            throw ValidationException::withMessages([
                'birth_date' =>
                    'The birth date cannot be after the recorded date of death.',
            ]);
        }

        $this->checkDuplicate($request, $data, $current);

        $current->update($data);
    }, 3);

    return redirect()
        ->route('residents.index')
        ->with('success', 'Resident updated successfully.');
}

    public function destroy(Resident $resident): RedirectResponse
{
    Gate::authorize('access-admin');

    $resident->delete();

    return redirect()
        ->route('residents.index')
        ->with(
            'success',
            'Resident removed from the active registry. '
            . 'The record has been retained for recovery.'
        );
}

    private function validatedProfile(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'sex' => ['required', 'in:Male,Female'],
            'sitio' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
        ]);

        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = Str::squish($data[$field]);
            }
        }

        return $data;
    }

    private function checkDuplicate(
        Request $request,
        array $data,
        ?Resident $current = null
    ): void {
        $normalize = fn (string $value): string =>
            Str::lower(Str::squish($value));

        $candidates = Resident::query()
            ->whereDate('birth_date', $data['birth_date'])
            ->when($current !== null, function ($query) use ($current) {
                $query->where('id', '!=', $current->id);
            })
            ->get([
                'id',
                'resident_number',
                'first_name',
                'last_name',
            ]);

        $match = $candidates->first(function ($candidate) use (
            $data,
            $normalize
        ) {
            return $normalize($candidate->first_name)
                    === $normalize($data['first_name'])
                && $normalize($candidate->last_name)
                    === $normalize($data['last_name']);
        });

        if ($match && ! $request->boolean('confirm_distinct_person')) {
            throw ValidationException::withMessages([
                'duplicate' =>
                    'Possible duplicate: resident reference '
                    . $match->resident_number
                    . ' has the same first name, last name, and birth date. '
                    . 'Review that record first. Only confirm a separate '
                    . 'person if you have verified that they are different.',
            ]);
        }
    }
}