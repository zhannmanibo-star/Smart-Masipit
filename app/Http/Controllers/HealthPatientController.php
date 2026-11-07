<?php

namespace App\Http\Controllers;

use App\Models\HealthPatient;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HealthPatientController extends Controller
{
    private function authorizeHealthStaff(
        Request $request,
        bool $write = false
    ): User {
        $actor = $request->user()?->fresh();

        abort_unless(
            $actor && $actor->canAccessHealthRecords(),
            403
        );

        Gate::forUser($actor)->authorize(
            $write ? 'manage-health-records' : 'access-health-records'
        );

        return $actor;
    }

    private function recordAccess(
        int $actorId,
        string $action,
        ?int $patientId = null
    ): void {
        DB::table('health_access_logs')->insert([
            'actor_id' => $actorId,
            'health_patient_id' => $patientId,
            'action' => $action,
            'occurred_at' => now(),
        ]);
    }

    public function index(Request $request): Response
    {
        $actor = $this->authorizeHealthStaff($request);

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) $request->input('search', ''));

        $patients = HealthPatient::query()
            ->with('resident')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('resident', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('resident_number', 'like', "%{$search}%");
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $this->recordAccess($actor->id, 'patient_registry_viewed');

        return response()
            ->view('health.patients.index', compact('patients', 'search'))
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function create(Request $request): Response
    {
        $actor = $this->authorizeHealthStaff($request, true);

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) $request->input('search', ''));

        $residents = Resident::query()
            ->whereNull('date_of_death')
            ->whereNotIn(
                'id',
                HealthPatient::query()->select('resident_id')
            )
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

        $this->recordAccess(
            $actor->id,
            'patient_registration_list_viewed'
        );

        return response()
            ->view('health.patients.create', compact('residents', 'search'))
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeHealthStaff($request, true);

        $mode = $request->validate([
            'registration_mode' => [
                'required',
                'string',
                'in:existing,new',
            ],
        ]);

        if ($mode['registration_mode'] === 'new') {
            return $this->storeNewResidentPatient($request);
        }

        $data = $request->validate([
            'resident_id' => ['required', 'integer', 'min:1'],
            'identity_confirmed' => ['accepted'],
        ]);

        try {
            $patient = DB::transaction(function () use ($request, $data) {
                $actor = $this->authorizeHealthStaff($request, true);

                $resident = Resident::query()
                    ->lockForUpdate()
                    ->find($data['resident_id']);

                if (! $resident || $resident->isDeceased()) {
                    throw ValidationException::withMessages([
                        'resident_id' =>
                            'Select an unarchived resident without a recorded death. '
                            . 'Refresh the page if the resident status changed.',
                    ]);
                }

                $existing = HealthPatient::query()
                    ->where('resident_id', $resident->id)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $patient = new HealthPatient();
                $patient->resident_id = $resident->id;
                $patient->registered_by = $actor->id;
                $patient->save();

                $this->recordAccess(
                    $actor->id,
                    'patient_registered',
                    $patient->id
                );

                return $patient;
            }, 3);
        } catch (QueryException $exception) {
            $this->authorizeHealthStaff($request, true);

            // Only recover an actual duplicate-key conflict.
            $sqlState = (string) ($exception->errorInfo[0] ?? '');
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);

            $isDuplicate = $sqlState === '23505'
                || (
                    $sqlState === '23000'
                    && in_array($driverCode, [1062, 19, 2067], true)
                );

            if (! $isDuplicate) {
                throw $exception;
            }

            $patient = HealthPatient::query()
                ->where('resident_id', $data['resident_id'])
                ->first();

            if (! $patient) {
                throw $exception;
            }
        }

        return redirect()
            ->route('health.patients.show', $patient)
            ->with(
                'success',
                'Patient record is available. No duplicate profile was created.'
            );
    }

    private function storeNewResidentPatient(
        Request $request
    ): RedirectResponse {
        foreach ([
            'first_name',
            'middle_name',
            'last_name',
            'sitio',
            'address',
            'contact_number',
        ] as $field) {
            $value = $request->input($field);

            if (is_string($value)) {
                $value = Str::squish($value);

                $request->merge([
                    $field => $value === '' ? null : $value,
                ]);
            }
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . now('Asia/Manila')->toDateString(),
            ],
            'sex' => ['required', 'in:Male,Female'],
            'sitio' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'new_resident_confirmed' => ['accepted'],
        ], [
            'new_resident_confirmed.accepted' =>
                'Confirm the patient’s identity, barangay residency, '
                . 'and that you checked for an existing record.',
        ]);

        $patient = DB::transaction(function () use ($request, $data) {
            $actor = $this->authorizeHealthStaff($request, true);

            $normalize = fn (string $value): string =>
                Str::lower(Str::squish($value));

            // Include archived records so this form cannot recreate
            // a matching archived or deceased resident unnoticed.
            $candidates = Resident::withTrashed()
                ->whereDate('birth_date', $data['birth_date'])
                ->lockForUpdate()
                ->get();

            $matches = $candidates->filter(function ($resident) use (
                $data,
                $normalize
            ) {
                return $normalize($resident->first_name)
                        === $normalize($data['first_name'])
                    && $normalize($resident->last_name)
                        === $normalize($data['last_name']);
            });

            if ($matches->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'duplicate' =>
                        'A possible resident record already exists with the '
                        . 'same first name, last name, and birth date. '
                        . 'Check the patient registry and the existing-resident '
                        . 'search first. If the record is archived, deceased, '
                        . 'or belongs to a different person, ask the resident '
                        . 'registry staff to review it. No new records were created.',
                ]);
            }

            // Create only a resident record, never a User account.
            $resident = new Resident();
            $resident->resident_number = (string) Str::uuid();
            $resident->first_name = $data['first_name'];
            $resident->middle_name = $data['middle_name'] ?? null;
            $resident->last_name = $data['last_name'];
            $resident->birth_date = $data['birth_date'];
            $resident->sex = $data['sex'];
            $resident->sitio = $data['sitio'];
            $resident->address = $data['address'];
            $resident->contact_number = $data['contact_number'] ?? null;
            $resident->save();

            $patient = new HealthPatient();
            $patient->resident_id = $resident->id;
            $patient->registered_by = $actor->id;
            $patient->save();

            $this->recordAccess(
                $actor->id,
                'resident_created_for_patient',
                $patient->id
            );

            $this->recordAccess(
                $actor->id,
                'patient_registered',
                $patient->id
            );

            return $patient;
        }, 3);

        return redirect()
            ->route('health.patients.show', $patient)
            ->with(
                'success',
                'Resident and patient records created successfully. '
                . 'No online account was created.'
            );
    }

    public function show(
        Request $request,
        HealthPatient $healthPatient
    ): Response {
        $actor = $this->authorizeHealthStaff($request);

        $healthPatient->load(['resident', 'registeredBy']);

        $this->recordAccess(
            $actor->id,
            'patient_profile_viewed',
            $healthPatient->id
        );

        $activity = DB::table('health_access_logs as logs')
            ->join('users', 'users.id', '=', 'logs.actor_id')
            ->where('logs.health_patient_id', $healthPatient->id)
            ->select('logs.*', 'users.name as actor_name')
            ->orderByDesc('logs.id')
            ->paginate(10);

        return response()
            ->view('health.patients.show', [
                'patient' => $healthPatient,
                'resident' => $healthPatient->resident,
                'activity' => $activity,
            ])
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}