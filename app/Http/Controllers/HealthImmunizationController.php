<?php

namespace App\Http\Controllers;

use App\Models\HealthImmunization;
use App\Models\HealthImmunizationCorrection;
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

class HealthImmunizationController extends Controller
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

    private function normalize(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if (is_string($request->input($field))) {
                $request->merge([
                    $field => trim($request->input($field)),
                ]);
            }
        }
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

        $immunizations = HealthImmunization::query()
            ->where('health_patient_id', $healthPatient->id)
            ->with([
                'recordedBy',
                'corrections' => function ($query) {
                    $query->orderBy('id');
                },
                'corrections.recordedBy',
            ])
            ->orderByDesc('administered_on')
            ->orderByDesc('id')
            ->paginate(10);

        $this->recordAccess(
            $actor->id,
            $healthPatient->id,
            'immunization_history_viewed'
        );

        return response()
            ->view('health.immunizations.index', [
                'patient' => $healthPatient,
                'resident' => $healthPatient->resident,
                'immunizations' => $immunizations,
                'submissionToken' => (string) Str::uuid(),
            ])
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function store(
        Request $request,
        HealthPatient $healthPatient
    ): RedirectResponse {
        $this->authorizeHealthStaff($request);

        $this->normalize($request, [
            'vaccine_name',
            'dose_label',
            'administered_by',
            'facility',
            'lot_number',
            'source_details',
            'notes',
        ]);

        $data = $request->validateWithBag('immunization', [
            'vaccine_name' => ['required', 'string', 'max:150'],
            'dose_label' => ['nullable', 'string', 'max:100'],
            'administered_on' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . now('Asia/Manila')->toDateString(),
            ],
            'administered_by' => ['nullable', 'string', 'max:150'],
            'facility' => ['nullable', 'string', 'max:200'],
            'lot_number' => ['nullable', 'string', 'max:100'],
            'source_details' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'submission_token' => ['required', 'uuid'],
            'confirmed' => ['accepted'],
        ]);

        try {
            $item = DB::transaction(function () use (
                $request,
                $healthPatient,
                $data
            ) {
                $actor = $this->authorizeHealthStaff($request);

                $resident = Resident::withTrashed()
                    ->lockForUpdate()
                    ->findOrFail($healthPatient->resident_id);

                $existing = HealthImmunization::query()
                    ->where('recorded_by', $actor->id)
                    ->where('submission_token', $data['submission_token'])
                    ->first();

                if ($existing) {
                    if (
                        (int) $existing->health_patient_id
                        !== (int) $healthPatient->id
                    ) {
                        throw ValidationException::withMessages([
                            'submission_token' =>
                                'This form token was used for another patient. '
                                . 'Reload the page.',
                        ])->errorBag('immunization');
                    }

                    return $existing;
                }

                if ($resident->trashed()) {
                    throw ValidationException::withMessages([
                        'administered_on' =>
                            'This resident is archived. New immunization '
                            . 'entries cannot be added.',
                    ])->errorBag('immunization');
                }

                $date = $data['administered_on'];

                if ($date < $resident->birth_date->format('Y-m-d')) {
                    throw ValidationException::withMessages([
                        'administered_on' =>
                            'The administration date cannot be before '
                            . 'the resident birth date.',
                    ])->errorBag('immunization');
                }

                if (
                    $resident->isDeceased()
                    && $date > $resident->date_of_death->format('Y-m-d')
                ) {
                    throw ValidationException::withMessages([
                        'administered_on' =>
                            'The administration date cannot be after '
                            . 'the recorded date of death.',
                    ])->errorBag('immunization');
                }

                $item = new HealthImmunization();
                $item->health_patient_id = $healthPatient->id;
                $item->recorded_by = $actor->id;
                $item->reference_number = 'HI-' . Str::ulid();
                $item->submission_token = $data['submission_token'];
                $item->vaccine_name = $data['vaccine_name'];
                $item->dose_label = $data['dose_label'] ?? null;
                $item->administered_on = $date;
                $item->administered_by = $data['administered_by'] ?? null;
                $item->facility = $data['facility'] ?? null;
                $item->lot_number = $data['lot_number'] ?? null;
                $item->source_details = $data['source_details'];
                $item->notes = $data['notes'] ?? null;
                $item->save();

                $this->recordAccess(
                    $actor->id,
                    $healthPatient->id,
                    'immunization_recorded'
                );

                return $item;
            }, 3);
        } catch (QueryException $exception) {
            $actor = $this->authorizeHealthStaff($request);

            $item = HealthImmunization::query()
                ->where('recorded_by', $actor->id)
                ->where('submission_token', $data['submission_token'])
                ->where('health_patient_id', $healthPatient->id)
                ->first();

            if (! $item) {
                throw $exception;
            }
        }

        return redirect()
            ->route('health.immunizations.index', $healthPatient)
            ->with(
                'success',
                'Immunization recorded. Reference: '
                . $item->reference_number
            );
    }

    public function correct(
        Request $request,
        HealthPatient $healthPatient,
        HealthImmunization $immunization
    ): RedirectResponse {
        $this->authorizeHealthStaff($request);

        abort_unless(
            (int) $immunization->health_patient_id
                === (int) $healthPatient->id,
            404
        );

        $this->normalize($request, [
            'reason',
            'correction',
            'correction_source',
        ]);

        $data = $request->validateWithBag('correction', [
            'immunization_id' => [
                'required',
                'integer',
                'in:' . $immunization->id,
            ],
            'correction_token' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'correction' => ['required', 'string', 'min:10', 'max:5000'],
            'correction_source' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            'correction_confirmed' => ['accepted'],
        ]);

        try {
            DB::transaction(function () use (
                $request,
                $healthPatient,
                $immunization,
                $data
            ) {
                $actor = $this->authorizeHealthStaff($request);

                $current = HealthImmunization::query()
                    ->where('health_patient_id', $healthPatient->id)
                    ->lockForUpdate()
                    ->findOrFail($immunization->id);

                $existing = HealthImmunizationCorrection::query()
                    ->where('recorded_by', $actor->id)
                    ->where('submission_token', $data['correction_token'])
                    ->first();

                if ($existing) {
                    if (
                        (int) $existing->health_immunization_id
                        !== (int) $current->id
                    ) {
                        throw ValidationException::withMessages([
                            'correction_token' =>
                                'This form token was already used elsewhere. '
                                . 'Reload the page.',
                        ])->errorBag('correction');
                    }

                    return;
                }

                $entry = new HealthImmunizationCorrection();
                $entry->health_immunization_id = $current->id;
                $entry->recorded_by = $actor->id;
                $entry->submission_token = $data['correction_token'];
                $entry->reason = $data['reason'];
                $entry->correction = $data['correction'];
                $entry->source_details = $data['correction_source'];
                $entry->save();

                $this->recordAccess(
                    $actor->id,
                    $healthPatient->id,
                    'immunization_correction_recorded'
                );
            }, 3);
        } catch (QueryException $exception) {
            $actor = $this->authorizeHealthStaff($request);

            $existing = HealthImmunizationCorrection::query()
                ->where('recorded_by', $actor->id)
                ->where('submission_token', $data['correction_token'])
                ->where('health_immunization_id', $immunization->id)
                ->first();

            if (! $existing) {
                throw $exception;
            }
        }

        return redirect()
            ->route('health.immunizations.index', $healthPatient)
            ->with(
                'success',
                'Correction recorded. The original entry has been retained.'
            );
    }
}