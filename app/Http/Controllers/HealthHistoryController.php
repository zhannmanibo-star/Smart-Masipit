<?php

namespace App\Http\Controllers;

use App\Models\HealthHistoryEntry;
use App\Models\HealthPatient;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HealthHistoryController extends Controller
{
    private function authorizeHealthStaff(Request $request): User
    {
        $actor = $request->user()?->fresh();

        abort_unless(
            $actor && $actor->canAccessHealthRecords(),
            403
        );

        Gate::forUser($actor)->authorize('manage-health-records');

        return $actor;
    }

    private function recordAccess(
        int $actorId,
        int $patientId,
        string $action
    ): void {
        DB::table('health_access_logs')->insert([
            'actor_id' => $actorId,
            'health_patient_id' => $patientId,
            'action' => $action,
            'occurred_at' => now(),
        ]);
    }

    public function index(
        Request $request,
        HealthPatient $healthPatient
    ): Response {
        $actor = $this->authorizeHealthStaff($request);

        $healthPatient->load('resident');

        $currentEntries = [];

        foreach (array_keys(HealthHistoryEntry::CATEGORIES) as $category) {
            $currentEntries[$category] = HealthHistoryEntry::query()
                ->with('recordedBy')
                ->where('health_patient_id', $healthPatient->id)
                ->where('category', $category)
                ->orderByDesc('version')
                ->first();
        }

        $entries = HealthHistoryEntry::query()
            ->with('recordedBy')
            ->where('health_patient_id', $healthPatient->id)
            ->orderByDesc('id')
            ->paginate(10);

        $this->recordAccess(
            $actor->id,
            $healthPatient->id,
            'health_history_viewed'
        );

        return response()
            ->view('health.history.index', [
                'patient' => $healthPatient,
                'resident' => $healthPatient->resident,
                'currentEntries' => $currentEntries,
                'entries' => $entries,
                'categories' => HealthHistoryEntry::CATEGORIES,
                'statuses' => HealthHistoryEntry::STATUSES,
                'sources' => HealthHistoryEntry::SOURCES,
            ])
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function store(
        Request $request,
        HealthPatient $healthPatient
    ): RedirectResponse {
        $this->authorizeHealthStaff($request);

        foreach ([
            'details',
            'source_details',
            'change_reason',
        ] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([
                    $field => trim($request->input($field)),
                ]);
            }
        }

        $data = $request->validate([
            'category' => [
                'required',
                Rule::in(array_keys(HealthHistoryEntry::CATEGORIES)),
            ],
            'finding_status' => [
                'required',
                Rule::in(array_keys(HealthHistoryEntry::STATUSES)),
            ],
            'details' => [
                'required_if:finding_status,reported',
                'prohibited_unless:finding_status,reported',
                'nullable',
                'string',
                'min:5',
                'max:10000',
            ],
            'information_source' => [
                'required',
                Rule::in(array_keys(HealthHistoryEntry::SOURCES)),
            ],
            'source_details' => [
                'required',
                'string',
                'min:5',
                'max:2000',
            ],
            'source_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . now('Asia/Manila')->toDateString(),
            ],
            'change_reason' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            'expected_version' => ['required', 'integer', 'min:0'],
            'submission_token' => ['required', 'uuid'],
            'confirmed' => ['accepted'],
        ]);

        try {
            DB::transaction(function () use (
                $request,
                $healthPatient,
                $data
            ) {
                $actor = $this->authorizeHealthStaff($request);

                $patient = HealthPatient::query()
                    ->lockForUpdate()
                    ->findOrFail($healthPatient->id);

                $existing = HealthHistoryEntry::query()
                    ->where('recorded_by', $actor->id)
                    ->where('submission_token', $data['submission_token'])
                    ->first();

                if ($existing) {
                    if (
                        (int) $existing->health_patient_id !== (int) $patient->id
                        || $existing->category !== $data['category']
                    ) {
                        throw ValidationException::withMessages([
                            'submission_token' =>
                                'This form token was already used elsewhere. '
                                . 'Reload the page.',
                        ]);
                    }

                    return;
                }

                $latest = HealthHistoryEntry::query()
                    ->where('health_patient_id', $patient->id)
                    ->where('category', $data['category'])
                    ->orderByDesc('version')
                    ->first();

                $version = $latest?->version ?? 0;

                if ($version !== (int) $data['expected_version']) {
                    throw ValidationException::withMessages([
                        'expected_version' =>
                            'Another entry was saved while this form was open. '
                            . 'Reload the page and review the latest version '
                            . 'before entering your update again.',
                    ]);
                }

                $entry = new HealthHistoryEntry();
                $entry->health_patient_id = $patient->id;
                $entry->recorded_by = $actor->id;
                $entry->category = $data['category'];
                $entry->version = $version + 1;
                $entry->finding_status = $data['finding_status'];
                $entry->details = $data['details'] ?? null;
                $entry->information_source = $data['information_source'];
                $entry->source_details = $data['source_details'];
                $entry->source_date = $data['source_date'];
                $entry->change_reason = $data['change_reason'];
                $entry->submission_token = $data['submission_token'];
                $entry->save();

                $this->recordAccess(
                    $actor->id,
                    $patient->id,
                    'health_history_recorded'
                );
            }, 3);
        } catch (QueryException $exception) {
            $actor = $this->authorizeHealthStaff($request);

            $existing = HealthHistoryEntry::query()
                ->where('recorded_by', $actor->id)
                ->where('submission_token', $data['submission_token'])
                ->where('health_patient_id', $healthPatient->id)
                ->where('category', $data['category'])
                ->first();

            if (! $existing) {
                $latestVersion = (int) HealthHistoryEntry::query()
                    ->where('health_patient_id', $healthPatient->id)
                    ->where('category', $data['category'])
                    ->max('version');

                if ($latestVersion !== (int) $data['expected_version']) {
                    throw ValidationException::withMessages([
                        'expected_version' =>
                            'The health history changed while saving. '
                            . 'Reload the page and review the latest version.',
                    ]);
                }

                throw $exception;
            }
        }

        return redirect()
            ->route('health.history.index', $healthPatient)
            ->with(
                'success',
                'Health history saved. Previous versions have been retained.'
            );
    }
}