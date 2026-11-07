<?php

namespace App\Http\Controllers;

use App\Models\HealthPatient;
use App\Models\MaternalRecord;
use App\Models\MaternalVisit;
use App\Models\Resident;
use App\Models\User;
use App\Support\MaternalFields;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaternalRecordController extends Controller
{
    private function actor(Request $request, bool $write = false): User
    {
        $actor = $request->user()?->fresh();

        abort_unless($actor && $actor->canAccessHealthRecords(), 403);

        Gate::forUser($actor)->authorize(
            $write ? 'manage-health-records' : 'access-health-records'
        );

        return $actor;
    }

    private function log(User $actor, HealthPatient $patient, string $action): void
    {
        DB::table('health_access_logs')->insert([
            'actor_id' => $actor->id,
            'health_patient_id' => $patient->id,
            'action' => $action,
            'occurred_at' => now(),
        ]);
    }

    private function page(string $view, array $data): Response
    {
        return response()
            ->view($view, $data)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function checkParent(
        HealthPatient $patient,
        MaternalRecord $record
    ): void {
        abort_unless(
            (int) $record->health_patient_id === (int) $patient->id,
            404
        );
    }

    private function residentForEntry(HealthPatient $patient): Resident
{
    $resident = Resident::withTrashed()
        ->lockForUpdate()
        ->find($patient->resident_id);

    if (! $resident || $resident->trashed()) {
        throw ValidationException::withMessages([
            'patient' =>
                'The resident record is archived or unavailable.',
        ]);
    }

    if ($resident->sex !== 'Female') {
        throw ValidationException::withMessages([
            'patient' =>
                'Maternal record registration and new antepartum visits '
                . 'require a resident record with sex recorded as Female. '
                . 'If the registry information is incorrect, refer it '
                . 'to authorized registry staff for correction.',
        ]);
    }

    return $resident;
}

    private function checkDate(Resident $resident, string $date): void
    {
        if ($date < $resident->birth_date->format('Y-m-d')) {
            throw ValidationException::withMessages([
                'date' => 'The entry date cannot be before the birth date.',
            ]);
        }

        if (
            $resident->date_of_death
            && $date > $resident->date_of_death->format('Y-m-d')
        ) {
            throw ValidationException::withMessages([
                'date' => 'Historical entries cannot be dated after the recorded death.',
            ]);
        }
    }

    private function normalize(Request $request, array $keys): void
    {
        $changes = [];

        foreach ($keys as $key) {
            $value = $request->input($key);

            if (is_string($value)) {
                $changes[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        $request->merge($changes);
    }

    public function index(Request $request, HealthPatient $healthPatient): Response
    {
        $actor = $this->actor($request);
        $resident = $healthPatient->resident;

        $records = MaternalRecord::query()
            ->where('health_patient_id', $healthPatient->id)
            ->with('recordedBy')
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->paginate(10);

        $this->log($actor, $healthPatient, 'maternal_records_viewed');

        return $this->page('health.maternal.index', [
            'patient' => $healthPatient,
            'resident' => $resident,
            'records' => $records,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(
        Request $request,
        HealthPatient $healthPatient
    ): RedirectResponse {
        $this->actor($request, true);

        $fieldRules = MaternalFields::rules(MaternalFields::RECORD);
        $this->normalize($request, array_keys($fieldRules));

        $rules = array_merge($fieldRules, [
            'record_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . now('Asia/Manila')->toDateString(),
            ],
            'clinic' => ['required', 'string', 'max:100'],
            'submission_token' => ['required', 'uuid'],
            'details_confirmed' => ['accepted'],
        ]);

        $rules['lmp'][] = 'before_or_equal:record_date';
       if ($request->filled('lmp')) {
    $rules['edc'][] = 'after_or_equal:lmp';
}

        $data = $request->validate(
            $rules,
            [],
            MaternalFields::labels(MaternalFields::RECORD)
        );

        $record = DB::transaction(function () use (
            $request,
            $healthPatient,
            $data,
            $fieldRules
        ) {
            $actor = $this->actor($request, true);

            // Serialize entries for this patient.
            $patient = HealthPatient::query()
                ->lockForUpdate()
                ->findOrFail($healthPatient->id);

            $existing = MaternalRecord::query()
                ->where('recorded_by', $actor->id)
                ->where('submission_token', $data['submission_token'])
                ->first();

            if ($existing) {
                $this->checkParent($patient, $existing);

                return $existing;
            }

            $resident = $this->residentForEntry($patient);
            $this->checkDate($resident, $data['record_date']);

            if (
                ! empty($data['lmp'])
                && $data['lmp'] < $resident->birth_date->format('Y-m-d')
            ) {
                throw ValidationException::withMessages([
                    'lmp' => 'The LMP date cannot be before the birth date.',
                ]);
            }

            // Pause repeated registration of the same recorded LMP.
            if (! empty($data['lmp'])) {
                $duplicate = MaternalRecord::query()
                    ->where('health_patient_id', $patient->id)
                    ->where('details->lmp', $data['lmp'])
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'lmp' =>
                            'This patient already has a maternal record with this LMP. '
                            . 'Open that record to add visits or correction notes.',
                    ]);
                }
            }

            $address = implode(', ', array_filter([
                $resident->address,
                $resident->sitio ? 'Sitio ' . $resident->sitio : null,
                'Barangay Masipit',
                'Calapan City',
                'Oriental Mindoro',
            ]));

            $record = new MaternalRecord();
            $record->health_patient_id = $patient->id;
            $record->recorded_by = $actor->id;
            $record->reference_number = 'MHR-' . Str::ulid();
            $record->submission_token = $data['submission_token'];
            $record->record_date = $data['record_date'];

            $record->patient_snapshot = [
                'resident_number' => $resident->resident_number,
                'first_name' => $resident->first_name,
                'middle_name' => $resident->middle_name,
                'last_name' => $resident->last_name,
                'birth_date' => $resident->birth_date->format('Y-m-d'),
                'age' => (int) $resident->birth_date->diffInYears(
                    Carbon::parse($data['record_date'])
                ),
                'address' => $address,
                'contact_number' => $resident->contact_number,
            ];

            $record->details = Arr::only($data, array_keys($fieldRules));
            $record->save();

            $this->log($actor, $patient, 'maternal_record_created');

            return $record;
        }, 3);

        return redirect()
            ->route('health.maternal.show', [$healthPatient, $record])
            ->with('success', 'Maternal draft record saved.');
    }

    public function show(
        Request $request,
        HealthPatient $healthPatient,
        MaternalRecord $maternalRecord
    ): Response {
        $actor = $this->actor($request);
        $this->checkParent($healthPatient, $maternalRecord);

        $this->log($actor, $healthPatient, 'maternal_record_viewed');

        return $this->page('health.maternal.show', array_merge(
            $this->recordData($healthPatient, $maternalRecord),
            [
                'visitToken' => (string) Str::uuid(),
                'addendumToken' => (string) Str::uuid(),
            ]
        ));
    }

    private function recordData(
        HealthPatient $patient,
        MaternalRecord $record
    ): array {
        $record->load('recordedBy');

        return [
            'patient' => $patient,
            'resident' => $patient->resident,
            'record' => $record,
            'visits' => $record->visits()
                ->with('recordedBy')
                ->orderBy('visit_date')
                ->orderBy('id')
                ->get(),
            'addendums' => DB::table('maternal_addendums as a')
                ->join('users', 'users.id', '=', 'a.recorded_by')
                ->where('a.maternal_record_id', $record->id)
                ->select('a.*', 'users.name as actor_name')
                ->orderBy('a.id')
                ->get(),
        ];
    }

    public function visit(
        Request $request,
        HealthPatient $healthPatient,
        MaternalRecord $maternalRecord
    ): RedirectResponse {
        $this->actor($request, true);
        $this->checkParent($healthPatient, $maternalRecord);

        $fieldRules = MaternalFields::rules(MaternalFields::VISIT);
        $this->normalize($request, array_keys($fieldRules));

        $data = $request->validate(array_merge($fieldRules, [
            'visit_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . now('Asia/Manila')->toDateString(),
            ],
            'submission_token' => ['required', 'uuid'],
            'details_confirmed' => ['accepted'],
        ]));

        if (! collect(Arr::only($data, array_keys($fieldRules)))
            ->contains(fn ($value) => $value !== null && $value !== '')) {
            throw ValidationException::withMessages([
                'visit' => 'Enter at least one observation or remark.',
            ]);
        }

        DB::transaction(function () use (
            $request,
            $healthPatient,
            $maternalRecord,
            $data,
            $fieldRules
        ) {
            $actor = $this->actor($request, true);

            $patient = HealthPatient::query()
                ->lockForUpdate()
                ->findOrFail($healthPatient->id);

            $record = MaternalRecord::query()->findOrFail($maternalRecord->id);
            $this->checkParent($patient, $record);

            $existing = MaternalVisit::query()
                ->where('recorded_by', $actor->id)
                ->where('submission_token', $data['submission_token'])
                ->first();

            if ($existing) {
                abort_unless(
                    (int) $existing->maternal_record_id === (int) $record->id,
                    409
                );

                return;
            }

            $resident = $this->residentForEntry($patient);
            $this->checkDate($resident, $data['visit_date']);

            $earliest = $record->details['lmp'] ?? null;

            if ($earliest && $data['visit_date'] < $earliest) {
                throw ValidationException::withMessages([
                    'visit_date' => 'The visit cannot be before the recorded LMP.',
                ]);
            }

            $visit = new MaternalVisit();
            $visit->maternal_record_id = $record->id;
            $visit->recorded_by = $actor->id;
            $visit->submission_token = $data['submission_token'];
            $visit->visit_date = $data['visit_date'];
            $visit->details = Arr::only($data, array_keys($fieldRules));
            $visit->save();

            $this->log($actor, $patient, 'maternal_visit_recorded');
        }, 3);

        return redirect()
            ->route('health.maternal.show', [$healthPatient, $maternalRecord])
            ->with('success', 'Antepartum visit recorded.');
    }

    public function addendum(
        Request $request,
        HealthPatient $healthPatient,
        MaternalRecord $maternalRecord
    ): RedirectResponse {
        $this->actor($request, true);
        $this->checkParent($healthPatient, $maternalRecord);
        $this->normalize($request, ['reason', 'addendum']);

        $data = $request->validate([
            'submission_token' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'addendum' => ['required', 'string', 'min:10', 'max:3000'],
            'details_confirmed' => ['accepted'],
        ]);

        DB::transaction(function () use (
            $request,
            $healthPatient,
            $maternalRecord,
            $data
        ) {
            $actor = $this->actor($request, true);

            $patient = HealthPatient::query()
                ->lockForUpdate()
                ->findOrFail($healthPatient->id);

            $record = MaternalRecord::query()->findOrFail($maternalRecord->id);
            $this->checkParent($patient, $record);

            $existing = DB::table('maternal_addendums')
                ->where('recorded_by', $actor->id)
                ->where('submission_token', $data['submission_token'])
                ->first();

            if ($existing) {
                abort_unless(
                    (int) $existing->maternal_record_id === (int) $record->id,
                    409
                );

                return;
            }

            DB::table('maternal_addendums')->insert([
                'maternal_record_id' => $record->id,
                'recorded_by' => $actor->id,
                'submission_token' => $data['submission_token'],
                'reason' => $data['reason'],
                'addendum' => $data['addendum'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->log($actor, $patient, 'maternal_addendum_recorded');
        }, 3);

        return redirect()
            ->route('health.maternal.show', [$healthPatient, $maternalRecord])
            ->with('success', 'Correction / clarification retained with the original record.');
    }

    public function print(
        Request $request,
        HealthPatient $healthPatient,
        MaternalRecord $maternalRecord
    ): Response {
        $actor = $this->actor($request);
        $this->checkParent($healthPatient, $maternalRecord);

        // This records opening the print preview, not successful physical printing.
        $this->log($actor, $healthPatient, 'maternal_print_preview_opened');

        return $this->page(
            'health.maternal.print',
            $this->recordData($healthPatient, $maternalRecord)
        );
    }
}