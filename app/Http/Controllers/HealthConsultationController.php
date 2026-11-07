<?php

namespace App\Http\Controllers;

use App\Models\HealthConsultation;
use App\Models\HealthConsultationAddendum;
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

class HealthConsultationController extends Controller
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

        $consultations = $healthPatient->consultations()
            ->with([
                'recordedBy',
                'addendums' => function ($query) {
                    $query->orderBy('id');
                },
                'addendums.recordedBy',
            ])
            ->orderByDesc('consultation_date')
            ->orderByDesc('id')
            ->paginate(10);

        $this->recordAccess(
            $actor->id,
            $healthPatient->id,
            'consultation_history_viewed'
        );

        return response()
            ->view('health.consultations.index', [
                'patient' => $healthPatient,
                'resident' => $healthPatient->resident,
                'consultations' => $consultations,
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

        foreach (['reason_for_visit', 'consultation_notes'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([
                    $field => trim($request->input($field)),
                ]);
            }
        }

        // Blank readings are stored as null, never as zero.
        // Do not cast before validation, so invalid input is rejected.
        foreach (['bp_systolic', 'bp_diastolic'] as $field) {
            $value = $request->input($field);

            if (is_string($value)) {
                $value = trim($value);

                $request->merge([
                    $field => $value === '' ? null : $value,
                ]);
            }
        }

        $data = $request->validateWithBag('consultation', [
            'consultation_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . now('Asia/Manila')->toDateString(),
            ],
            'reason_for_visit' => [
                'required',
                'string',
                'min:5',
                'max:2000',
            ],
            'consultation_notes' => [
                'nullable',
                'string',
                'max:10000',
            ],
            'bp_systolic' => [
                'bail',
                'required_with:bp_diastolic',
                'nullable',
                'integer',
                'between:1,999',
            ],
            'bp_diastolic' => [
                'bail',
                'required_with:bp_systolic',
                'nullable',
                'integer',
                'between:1,999',
            ],
            'submission_token' => ['required', 'uuid'],
            'details_confirmed' => ['accepted'],
        ], [
            'bp_systolic.required_with' =>
                'Enter the systolic reading, or leave both BP fields blank.',
            'bp_diastolic.required_with' =>
                'Enter the diastolic reading, or leave both BP fields blank.',
            'bp_systolic.integer' =>
                'Systolic BP must be a whole number in mmHg.',
            'bp_diastolic.integer' =>
                'Diastolic BP must be a whole number in mmHg.',
            'bp_systolic.between' =>
                'Systolic BP must contain a number from 1 to 999.',
            'bp_diastolic.between' =>
                'Diastolic BP must contain a number from 1 to 999.',
        ]);

        try {
            $consultation = DB::transaction(function () use (
                $request,
                $healthPatient,
                $data
            ) {
                $actor = $this->authorizeHealthStaff($request);

                $resident = Resident::withTrashed()
                    ->lockForUpdate()
                    ->findOrFail($healthPatient->resident_id);

                $existing = HealthConsultation::query()
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
                                'This form token was already used for another patient. '
                                . 'Reload the page.',
                        ])->errorBag('consultation');
                    }

                    return $existing;
                }

                if ($resident->trashed()) {
                    throw ValidationException::withMessages([
                        'consultation_date' =>
                            'This resident is archived. Existing consultation '
                            . 'records remain available, but new entries are blocked.',
                    ])->errorBag('consultation');
                }

                $date = $data['consultation_date'];

                if ($date < $resident->birth_date->format('Y-m-d')) {
                    throw ValidationException::withMessages([
                        'consultation_date' =>
                            'The consultation date cannot be before the birth date.',
                    ])->errorBag('consultation');
                }

                if (
                    $resident->isDeceased()
                    && $date > $resident->date_of_death->format('Y-m-d')
                ) {
                    throw ValidationException::withMessages([
                        'consultation_date' =>
                            'The consultation date cannot be after the '
                            . 'recorded date of death.',
                    ])->errorBag('consultation');
                }

                $item = new HealthConsultation();
                $item->health_patient_id = $healthPatient->id;
                $item->recorded_by = $actor->id;
                $item->reference_number = 'HC-' . Str::ulid();
                $item->submission_token = $data['submission_token'];
                $item->consultation_date = $date;
                $item->reason_for_visit = $data['reason_for_visit'];
                $item->consultation_notes =
                    ($data['consultation_notes'] ?? null) ?: null;

                $item->bp_systolic = $data['bp_systolic'] ?? null;
                $item->bp_diastolic = $data['bp_diastolic'] ?? null;

                $item->save();

                $this->recordAccess(
                    $actor->id,
                    $healthPatient->id,
                    'consultation_recorded'
                );

                return $item;
            }, 3);
        } catch (QueryException $exception) {
            $actor = $this->authorizeHealthStaff($request);

            $consultation = HealthConsultation::query()
                ->where('recorded_by', $actor->id)
                ->where('submission_token', $data['submission_token'])
                ->where('health_patient_id', $healthPatient->id)
                ->first();

            if (! $consultation) {
                throw $exception;
            }
        }

        return redirect()
            ->route('health.consultations.index', $healthPatient)
            ->with(
                'success',
                'Consultation recorded. Reference: '
                . $consultation->reference_number
            );
    }

    public function addendum(
        Request $request,
        HealthPatient $healthPatient,
        HealthConsultation $consultation
    ): RedirectResponse {
        $this->authorizeHealthStaff($request);

        abort_unless(
            (int) $consultation->health_patient_id
                === (int) $healthPatient->id,
            404
        );

        foreach (['reason', 'addendum'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([
                    $field => trim($request->input($field)),
                ]);
            }
        }

        $data = $request->validateWithBag('addendum', [
            'consultation_id' => [
                'required',
                'integer',
                'in:' . $consultation->id,
            ],
            'addendum_token' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'addendum' => ['required', 'string', 'min:10', 'max:10000'],
            'correction_confirmed' => ['accepted'],
        ]);

        try {
            DB::transaction(function () use (
                $request,
                $healthPatient,
                $consultation,
                $data
            ) {
                $actor = $this->authorizeHealthStaff($request);

                $current = HealthConsultation::query()
                    ->where('health_patient_id', $healthPatient->id)
                    ->lockForUpdate()
                    ->findOrFail($consultation->id);

                $existing = HealthConsultationAddendum::query()
                    ->where('recorded_by', $actor->id)
                    ->where('submission_token', $data['addendum_token'])
                    ->first();

                if ($existing) {
                    if (
                        (int) $existing->health_consultation_id
                        !== (int) $current->id
                    ) {
                        throw ValidationException::withMessages([
                            'addendum_token' =>
                                'This form token was already used. Reload the page.',
                        ])->errorBag('addendum');
                    }

                    return;
                }

                $entry = new HealthConsultationAddendum();
                $entry->health_consultation_id = $current->id;
                $entry->recorded_by = $actor->id;
                $entry->submission_token = $data['addendum_token'];
                $entry->reason = $data['reason'];
                $entry->addendum = $data['addendum'];
                $entry->save();

                $this->recordAccess(
                    $actor->id,
                    $healthPatient->id,
                    'consultation_addendum_recorded'
                );
            }, 3);
        } catch (QueryException $exception) {
            $actor = $this->authorizeHealthStaff($request);

            $existing = HealthConsultationAddendum::query()
                ->where('recorded_by', $actor->id)
                ->where('submission_token', $data['addendum_token'])
                ->where('health_consultation_id', $consultation->id)
                ->first();

            if (! $existing) {
                throw $exception;
            }
        }

        return redirect()
            ->route('health.consultations.index', $healthPatient)
            ->with(
                'success',
                'Correction or clarification recorded. '
                . 'The original consultation has been retained.'
            );
    }
}