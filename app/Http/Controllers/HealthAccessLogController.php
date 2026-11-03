<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class HealthAccessLogController extends Controller
{
    private const ACTIONS = [
        'patient_registry_viewed' => 'Opened patient registry',
        'patient_registration_list_viewed' => 'Opened patient registration',
        'patient_registered' => 'Registered a patient',
        'patient_profile_viewed' => 'Opened patient profile',

        'consultation_history_viewed' => 'Viewed consultation history',
        'consultation_recorded' => 'Recorded a consultation',
        'consultation_addendum_recorded' => 'Added consultation clarification',

        'health_history_viewed' => 'Viewed health history',
        'health_history_recorded' => 'Recorded health history',

        'immunization_history_viewed' => 'Viewed immunization history',
        'immunization_recorded' => 'Recorded an immunization',
        'immunization_correction_recorded' => 'Added immunization correction',

        'health_access_history_viewed' => 'Opened health access history',
    ];

    public function index(Request $request): Response
    {
        $actor = $request->user()?->fresh();

        abort_unless(
            $actor && $actor->canAccessHealthRecords(),
            403
        );

        Gate::forUser($actor)->authorize('view-health-access-logs');

        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();

        // The initial page shows the latest seven calendar days.
        if (! $request->has('from')) {
            $request->merge([
                'from' => $today->subDays(6)->toDateString(),
            ]);
        }

        if (! $request->has('to')) {
            $request->merge([
                'to' => $today->toDateString(),
            ]);
        }

        if (is_string($request->input('search'))) {
            $request->merge([
                'search' => trim($request->input('search')),
            ]);
        }

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'action' => [
                'nullable',
                Rule::in(array_keys(self::ACTIONS)),
            ],
            'from' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . $today->toDateString(),
            ],
            'to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
                'before_or_equal:' . $today->toDateString(),
            ],
        ]);

        $search = $data['search'] ?? '';
        $actorId = $data['actor_id'] ?? null;
        $action = $data['action'] ?? '';

        $databaseTimezone = config('app.timezone', 'UTC');

        $start = CarbonImmutable::parse(
            $data['from'],
            'Asia/Manila'
        )->startOfDay()->setTimezone($databaseTimezone);

        // Exclusive next-day boundary includes the entire selected end date.
        $end = CarbonImmutable::parse(
            $data['to'],
            'Asia/Manila'
        )->startOfDay()->addDay()->setTimezone($databaseTimezone);

        // Record opening the audit page without storing search terms
        // or any clinical information.
        DB::table('health_access_logs')->insert([
            'actor_id' => $actor->id,
            'health_patient_id' => null,
            'action' => 'health_access_history_viewed',
            'occurred_at' => now(),
        ]);

        $logs = DB::table('health_access_logs as logs')
            ->leftJoin('users as actors', 'actors.id', '=', 'logs.actor_id')
            ->leftJoin(
                'health_patients as patients',
                'patients.id',
                '=',
                'logs.health_patient_id'
            )
            ->leftJoin(
                'residents',
                'residents.id',
                '=',
                'patients.resident_id'
            )
            ->where('logs.occurred_at', '>=', $start)
            ->where('logs.occurred_at', '<', $end)
            ->when($actorId !== null, function ($query) use ($actorId) {
                $query->where('logs.actor_id', $actorId);
            })
            ->when($action !== '', function ($query) use ($action) {
                $query->where('logs.action', $action);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where(
                        'residents.resident_number',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhere('residents.first_name', 'like', "%{$search}%")
                        ->orWhere('residents.middle_name', 'like', "%{$search}%")
                        ->orWhere('residents.last_name', 'like', "%{$search}%");
                });
            })
            ->select([
                'logs.id',
                'logs.actor_id',
                'logs.health_patient_id',
                'logs.action',
                'logs.occurred_at',
                'actors.name as actor_name',
                'residents.resident_number',
                'residents.first_name',
                'residents.middle_name',
                'residents.last_name',
            ])
            ->orderByDesc('logs.occurred_at')
            ->orderByDesc('logs.id')
            ->paginate(25)
            ->withQueryString();

        // Include people who have historical entries, even if their
        // account role has since changed.
        $staff = User::query()
            ->whereIn(
                'id',
                DB::table('health_access_logs')->select('actor_id')
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        return response()
            ->view('health.access-history', [
                'logs' => $logs,
                'staff' => $staff,
                'actions' => self::ACTIONS,
                'search' => $search,
                'actorId' => $actorId,
                'action' => $action,
                'from' => $data['from'],
                'to' => $data['to'],
            ])
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}