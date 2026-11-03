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
use Illuminate\Validation\ValidationException;

class HealthPatientController extends Controller
{
    private function authorizeHealthStaff(
        Request $request,
        bool $write = false
    ): User {
        $actor = $request->user()->fresh();

        abort_unless($actor && $actor->canAccessHealthRecords(), 403);

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
                        ->orWhere('resident_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        $this->recordAccess($actor->id, 'patient_registration_list_viewed');

        return response()
            ->view('health.patients.create', compact('residents', 'search'))
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeHealthStaff($request, true);

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
            // The unique resident_id prevents duplicate patient registration.
            $this->authorizeHealthStaff($request, true);

            $patient = HealthPatient::query()
                ->where('resident_id', $data['resident_id'])
                ->first();

            if (! $patient) {
                throw $exception;
            }
        }

        return redirect()
            ->route('health.patients.show', $patient)
            ->with('success', 'Patient record is available. No duplicate profile was created.');
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