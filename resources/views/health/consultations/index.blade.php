@extends('layouts.app')

@section('title', 'Consultation Records')
@section('description', 'Record consultations, blood pressure readings, and preserved corrections.')

@section('actions')
    <a
        class="button secondary"
        href="{{ route('health.patients.show', $patient) }}"
    >
        Back to patient profile
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>
            {{ $resident?->last_name }},
            {{ $resident?->first_name }}
            {{ $resident?->middle_name }}
        </h2>

        <p class="reference muted">
            {{ $resident?->resident_number }}
        </p>

        <p>
            <strong>Birth date:</strong>
            {{ $resident?->birth_date?->format('M d, Y') ?? 'Unavailable' }}
        </p>

        @if ($resident?->isDeceased())
            <p class="errors" role="status">
                Recorded date of death:
                {{ $resident->date_of_death->format('M d, Y') }}.
                Only historical consultations on or before this date
                may be recorded.
            </p>
        @endif

        @if (! $resident || $resident->trashed())
            <p class="errors" role="status">
                This resident is archived or unavailable.
                New consultations cannot be added.
                Existing records remain available for review and clarification.
            </p>
        @endif
    </section>

    @if ($resident && ! $resident->trashed())
        <section class="card">
            <h2>Record consultation</h2>

            <p class="muted">
                Enter information from the actual consultation or its
                verified source record. The system records your account
                and the encoding time automatically.
            </p>

            @if ($errors->consultation->any())
                <div class="errors" role="alert">
                    <strong>Please correct the following:</strong>

                    <ul>
                        @foreach ($errors->consultation->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('health.consultations.store', $patient) }}"
            >
                @csrf

                <input
                    type="hidden"
                    name="submission_token"
                    value="{{ old('submission_token', $submissionToken) }}"
                >

                <div>
                    <label for="consultation_date">
                        Consultation date *
                    </label>

                    <input
                        id="consultation_date"
                        name="consultation_date"
                        type="date"
                        min="{{ $resident->birth_date->format('Y-m-d') }}"
                        max="{{ $resident->isDeceased()
                            ? min(
                                $resident->date_of_death->format('Y-m-d'),
                                now('Asia/Manila')->toDateString()
                            )
                            : now('Asia/Manila')->toDateString() }}"
                        value="{{ old('consultation_date') }}"
                        required
                    >

                    <p class="muted">
                        Use the actual consultation date, even when
                        encoding a previous consultation.
                    </p>
                </div>

                <div style="margin-top: 18px;">
                    <label for="reason_for_visit">
                        Reason for visit *
                    </label>

                    <textarea
                        id="reason_for_visit"
                        name="reason_for_visit"
                        rows="3"
                        minlength="5"
                        maxlength="2000"
                        required
                        style="width: 100%; padding: 12px; font: inherit;"
                    >{{ old('reason_for_visit') }}</textarea>
                </div>

                <fieldset
                    style="margin: 24px 0; padding: 18px; border: 1px solid #bac8c1; border-radius: 8px; min-width: 0;"
                >
                    <legend style="font-weight: bold; padding: 0 8px;">
                        Blood pressure
                    </legend>

                    <p id="bp-help" class="muted">
                        Enter the recorded reading in mmHg.
                        Leave both fields blank if blood pressure
                        was not measured or is not documented.
                    </p>

                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); gap: 18px;"
                    >
                        <div>
                            <label for="bp_systolic">
                                Systolic (mmHg)
                            </label>

                            <input
                                id="bp_systolic"
                                name="bp_systolic"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                max="999"
                                step="1"
                                value="{{ old('bp_systolic') }}"
                                placeholder="e.g. 120"
                                aria-describedby="bp-help"
                                style="width: 100%;"
                            >
                        </div>

                        <div>
                            <label for="bp_diastolic">
                                Diastolic (mmHg)
                            </label>

                            <input
                                id="bp_diastolic"
                                name="bp_diastolic"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                max="999"
                                step="1"
                                value="{{ old('bp_diastolic') }}"
                                placeholder="e.g. 80"
                                aria-describedby="bp-help"
                                style="width: 100%;"
                            >
                        </div>
                    </div>

                    <p class="muted">
                        Enter both values when recording a reading.
                        For example, enter 120 and 80 separately.
                    </p>
                </fieldset>

                <div style="margin-top: 18px;">
                    <label for="consultation_notes">
                        Consultation notes
                    </label>

                    <textarea
                        id="consultation_notes"
                        name="consultation_notes"
                        rows="6"
                        maxlength="10000"
                        style="width: 100%; padding: 12px; font: inherit;"
                    >{{ old('consultation_notes') }}</textarea>

                    <p class="muted">
                        Record relevant information documented by the
                        responsible health personnel. Additional BP readings
                        and measurement details may be recorded here.
                    </p>
                </div>

                <label style="display: flex; gap: 10px; margin-top: 18px;">
                    <input
                        type="checkbox"
                        name="details_confirmed"
                        value="1"
                        required
                        style="width: auto;"
                    >

                    <span>
                        I verified the patient, consultation date,
                        blood pressure values if provided,
                        and the information being recorded.
                    </span>
                </label>

                <p class="muted">
                    After saving, corrections will be recorded as separate
                    entries. The original consultation will remain visible.
                </p>

                <button type="submit">Save consultation</button>
            </form>
        </section>
    @endif

    <section class="card">
        <h2>
            Consultation history
            <span class="muted">({{ $consultations->total() }})</span>
        </h2>

        @if ($errors->addendum->any())
            <div class="errors" role="alert">
                <strong>Please correct the clarification form:</strong>

                <ul>
                    @foreach ($errors->addendum->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @forelse ($consultations as $consultation)
            @php
                $isFailedAddendum =
                    $errors->addendum->any()
                    && (string) old('consultation_id')
                        === (string) $consultation->id;
            @endphp

            <article
                id="consultation-{{ $consultation->id }}"
                style="padding: 20px 0; border-bottom: 1px solid #d4e2da;"
            >
                <h3>
                    {{ $consultation->consultation_date->format('M d, Y') }}
                </h3>

                <p class="reference muted">
                    {{ $consultation->reference_number }}
                </p>

                <p class="muted">
                    Recorded by
                    {{ $consultation->recordedBy?->name ?? 'Unavailable' }}
                    on
                    {{ $consultation->created_at->copy()
                        ->timezone('Asia/Manila')
                        ->format('M d, Y · h:i A') }}
                    (Philippine time)
                </p>

                <div
                    style="margin: 18px 0; padding: 16px; background: #f5faf7; border: 1px solid #d4e2da; border-radius: 8px;"
                >
                    <h4 style="margin: 0 0 8px;">
                        Original blood pressure reading
                    </h4>

                    @if (
                        $consultation->bp_systolic !== null
                        && $consultation->bp_diastolic !== null
                    )
                        <p style="margin: 0; font-size: 24px;">
                            <strong>{{ $consultation->bp_systolic }}/{{ $consultation->bp_diastolic }}</strong>
                            <span style="font-size: 16px;">mmHg</span>
                        </p>
                    @elseif (
                        $consultation->bp_systolic !== null
                        || $consultation->bp_diastolic !== null
                    )
                        <p style="margin: 0;">
                            Incomplete reading — review the source record.
                        </p>
                    @else
                        <p class="muted" style="margin: 0;">
                            Not recorded.
                        </p>
                    @endif

                    @if ($consultation->addendums->isNotEmpty())
                        <p class="muted" style="margin-bottom: 0;">
                            This consultation has corrections or clarifications.
                            Read the entries below alongside the original record.
                        </p>
                    @endif
                </div>

                <h4>Reason for visit</h4>

                <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $consultation->reason_for_visit }}</p>

                <h4>Original consultation notes</h4>

                <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $consultation->consultation_notes ?: 'No additional notes recorded.' }}</p>

                @if ($consultation->addendums->isNotEmpty())
                    <h4>Corrections and clarifications</h4>

                    @foreach ($consultation->addendums as $entry)
                        <div
                            style="margin: 12px 0; padding: 16px; border-left: 4px solid #176b4c; background: #f5faf7;"
                        >
                            <p class="muted">
                                Entry #{{ $entry->id }} ·
                                {{ $entry->recordedBy?->name ?? 'Unavailable' }}
                                ·
                                {{ $entry->created_at->copy()
                                    ->timezone('Asia/Manila')
                                    ->format('M d, Y · h:i A') }}
                                (Philippine time)
                            </p>

                            <strong>Reason</strong>

                            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $entry->reason }}</p>

                            <strong>Correction or clarification</strong>

                            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $entry->addendum }}</p>
                        </div>
                    @endforeach
                @endif

                <details
                    style="margin-top: 18px;"
                    @if ($isFailedAddendum) open @endif
                >
                    <summary style="cursor: pointer; font-weight: bold;">
                        Add correction or clarification
                    </summary>

                    <p class="muted">
                        Identify the information being corrected and its
                        correct value. For a BP correction, state the original
                        and corrected readings in mmHg and the verified source.
                        This adds a dated entry without overwriting
                        the original consultation.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('health.consultations.addendums.store', [
                            'healthPatient' => $patient,
                            'consultation' => $consultation,
                        ]) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="consultation_id"
                            value="{{ $consultation->id }}"
                        >

                        <input
                            type="hidden"
                            name="addendum_token"
                            value="{{ $isFailedAddendum
                                ? old('addendum_token')
                                : (string) \Illuminate\Support\Str::uuid() }}"
                        >

                        <div>
                            <label for="reason-{{ $consultation->id }}">
                                Reason for correction or clarification *
                            </label>

                            <textarea
                                id="reason-{{ $consultation->id }}"
                                name="reason"
                                rows="3"
                                minlength="10"
                                maxlength="2000"
                                required
                                style="width: 100%; padding: 12px; font: inherit;"
                            >{{ $isFailedAddendum ? old('reason') : '' }}</textarea>
                        </div>

                        <div style="margin-top: 18px;">
                            <label for="addendum-{{ $consultation->id }}">
                                Correction or clarification *
                            </label>

                            <textarea
                                id="addendum-{{ $consultation->id }}"
                                name="addendum"
                                rows="5"
                                minlength="10"
                                maxlength="10000"
                                required
                                style="width: 100%; padding: 12px; font: inherit;"
                            >{{ $isFailedAddendum ? old('addendum') : '' }}</textarea>
                        </div>

                        <label style="display: flex; gap: 10px; margin-top: 18px;">
                            <input
                                type="checkbox"
                                name="correction_confirmed"
                                value="1"
                                required
                                style="width: auto;"
                            >

                            <span>
                                I verified this correction or clarification
                                and its association with this consultation.
                            </span>
                        </label>

                        <button type="submit">
                            Save correction or clarification
                        </button>
                    </form>
                </details>
            </article>
        @empty
            <p class="empty">No consultations recorded for this patient.</p>
        @endforelse

        <div class="pagination">
            <span class="muted">
                Showing {{ $consultations->firstItem() ?? 0 }}
                to {{ $consultations->lastItem() ?? 0 }}
                of {{ $consultations->total() }} consultations
            </span>

            @if ($consultations->hasPages())
                <nav
                    class="pagination-links"
                    aria-label="Consultation history pages"
                >
                    @if ($consultations->previousPageUrl())
                        <a href="{{ $consultations->previousPageUrl() }}">
                            Previous
                        </a>
                    @endif

                    <span>
                        {{ $consultations->currentPage() }}
                        / {{ $consultations->lastPage() }}
                    </span>

                    @if ($consultations->nextPageUrl())
                        <a href="{{ $consultations->nextPageUrl() }}">
                            Next
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </section>

    <script>
        (() => {
            const systolic = document.getElementById('bp_systolic');
            const diastolic = document.getElementById('bp_diastolic');

            if (!systolic || !diastolic) {
                return;
            }

            function updateRequiredFields() {
                systolic.required = diastolic.value !== '';
                diastolic.required = systolic.value !== '';
            }

            systolic.addEventListener('input', updateRequiredFields);
            diastolic.addEventListener('input', updateRequiredFields);
            window.addEventListener('pageshow', updateRequiredFields);

            updateRequiredFields();
        })();
    </script>
@endsection