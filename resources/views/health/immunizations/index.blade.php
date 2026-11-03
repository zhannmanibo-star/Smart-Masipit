@extends('layouts.app')

@section('title', 'Immunization Records')
@section('description', 'Record administered vaccines and preserve source details and corrections.')

@section('actions')
    <a class="button secondary"
       href="{{ route('health.patients.show', $patient) }}">
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
            <p class="muted">
                Recorded date of death:
                {{ $resident->date_of_death->format('M d, Y') }}.
                Historical vaccine administrations on or before this
                date may still be encoded.
            </p>
        @endif

        @if (! $resident || $resident->trashed())
            <p class="errors" role="status">
                This resident is archived or unavailable.
                New immunization entries are blocked.
                Existing records and correction history remain available.
            </p>
        @endif
    </section>

    @if ($resident && ! $resident->trashed())
        <section class="card">
            <h2>Record an administered vaccine</h2>

            <p class="muted">
                Review existing entries below before adding a record.
                Copy information from the verified source.
                Leave optional fields blank when they are not recorded.
            </p>

            @if ($errors->immunization->any())
                <div class="errors" role="alert">
                    <strong>Please correct the following:</strong>

                    <ul>
                        @foreach ($errors->immunization->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST"
                  action="{{ route('health.immunizations.store', $patient) }}">
                @csrf

                <input type="hidden"
                       name="submission_token"
                       value="{{ old('submission_token', $submissionToken) }}">

                <div class="grid">
                    <div>
                        <label for="vaccine_name">Vaccine name *</label>

                        <input id="vaccine_name"
                               name="vaccine_name"
                               value="{{ old('vaccine_name') }}"
                               maxlength="150"
                               required>
                    </div>

                    <div>
                        <label for="dose_label">Dose label</label>

                        <input id="dose_label"
                               name="dose_label"
                               value="{{ old('dose_label') }}"
                               maxlength="100">

                        <p class="muted">
                            Use the dose label written in the source record.
                        </p>
                    </div>

                    <div>
                        <label for="administered_on">
                            Actual date administered *
                        </label>

                        <input id="administered_on"
                               name="administered_on"
                               type="date"
                               min="{{ $resident->birth_date->format('Y-m-d') }}"
                               max="{{ $resident->isDeceased()
                                   ? min(
                                       $resident->date_of_death->format('Y-m-d'),
                                       now('Asia/Manila')->toDateString()
                                   )
                                   : now('Asia/Manila')->toDateString() }}"
                               value="{{ old('administered_on') }}"
                               required>
                    </div>

                    <div>
                        <label for="administered_by">
                            Administering health personnel
                        </label>

                        <input id="administered_by"
                               name="administered_by"
                               value="{{ old('administered_by') }}"
                               maxlength="150">

                        <p class="muted">
                            The person who administered the vaccine,
                            as recorded in the source.
                        </p>
                    </div>

                    <div>
                        <label for="facility">Administering facility</label>

                        <input id="facility"
                               name="facility"
                               value="{{ old('facility') }}"
                               maxlength="200">
                    </div>

                    <div>
                        <label for="lot_number">Lot / batch number</label>

                        <input id="lot_number"
                               name="lot_number"
                               value="{{ old('lot_number') }}"
                               maxlength="100">
                    </div>
                </div>

                <div style="margin-top: 18px;">
                    <label for="source_details">Source record details *</label>

                    <textarea id="source_details"
                              name="source_details"
                              rows="3"
                              minlength="10"
                              maxlength="2000"
                              required
                              style="width: 100%; padding: 12px; font: inherit;">{{ old('source_details') }}</textarea>

                    <p class="muted">
                        Identify the vaccination card, facility record,
                        or other verified document used for this entry.
                    </p>
                </div>

                <div style="margin-top: 18px;">
                    <label for="notes">Additional recorded notes</label>

                    <textarea id="notes"
                              name="notes"
                              rows="3"
                              maxlength="5000"
                              style="width: 100%; padding: 12px; font: inherit;">{{ old('notes') }}</textarea>
                </div>

                <label style="display: flex; gap: 10px; margin-top: 18px;">
                    <input type="checkbox"
                           name="confirmed"
                           value="1"
                           required
                           style="width: auto;">

                    <span>
                        I verified the patient and source record,
                        reviewed existing entries, and confirmed that
                        this records a vaccine already administered.
                    </span>
                </label>

                <button type="submit">Save immunization record</button>
            </form>
        </section>
    @endif

    <section class="card">
        <h2>
            Recorded immunizations
            <span class="muted">({{ $immunizations->total() }})</span>
        </h2>

        <p class="muted">
            This is the number of saved entries, not an assessment
            of vaccination completion.
        </p>

        @if ($errors->correction->any())
            <div class="errors" role="alert">
                <strong>Please correct the correction form:</strong>

                <ul>
                    @foreach ($errors->correction->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @forelse ($immunizations as $item)
            @php
                $failedCorrection =
                    $errors->correction->any()
                    && (string) old('immunization_id')
                        === (string) $item->id;
            @endphp

            <article style="padding: 20px 0; border-bottom: 1px solid #d4e2da;">
                <h3>{{ $item->vaccine_name }}</h3>

                <p class="reference muted">
                    {{ $item->reference_number }}
                </p>

                @if ($item->corrections->isNotEmpty())
                    <p role="status">
                        <strong>
                            This entry has corrections.
                            Read the correction history below together
                            with the original details.
                        </strong>
                    </p>
                @endif

                <h4>Original recorded details</h4>

                <dl>
                    <dt><strong>Dose label</strong></dt>
                    <dd>{{ $item->dose_label ?: 'Not recorded' }}</dd>

                    <dt><strong>Date administered</strong></dt>
                    <dd>{{ $item->administered_on->format('M d, Y') }}</dd>

                    <dt><strong>Administering personnel</strong></dt>
                    <dd>{{ $item->administered_by ?: 'Not recorded' }}</dd>

                    <dt><strong>Facility</strong></dt>
                    <dd>{{ $item->facility ?: 'Not recorded' }}</dd>

                    <dt><strong>Lot / batch number</strong></dt>
                    <dd>{{ $item->lot_number ?: 'Not recorded' }}</dd>
                </dl>

                <h4>Source record</h4>

                <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $item->source_details }}</p>

                @if ($item->notes)
                    <h4>Additional recorded notes</h4>

                    <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $item->notes }}</p>
                @endif

                <p class="muted">
                    Encoded by
                    {{ $item->recordedBy?->name ?? 'Unavailable' }}
                    on
                    {{ $item->created_at->copy()
                        ->timezone('Asia/Manila')
                        ->format('M d, Y · h:i A') }}
                    (Philippine time)
                </p>

                @if ($item->corrections->isNotEmpty())
                    <h4>Correction history</h4>

                    @foreach ($item->corrections as $entry)
                        <div style="padding: 16px; margin: 12px 0; background: #f5faf7; border-left: 4px solid #176b4c;">
                            <p class="muted">
                                Correction #{{ $entry->id }} ·
                                {{ $entry->recordedBy?->name ?? 'Unavailable' }}
                                ·
                                {{ $entry->created_at->copy()
                                    ->timezone('Asia/Manila')
                                    ->format('M d, Y · h:i A') }}
                                (Philippine time)
                            </p>

                            <strong>Reason</strong>
                            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $entry->reason }}</p>

                            <strong>Correction</strong>
                            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $entry->correction }}</p>

                            <strong>Supporting source</strong>
                            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $entry->source_details }}</p>
                        </div>
                    @endforeach
                @endif

                <details @if ($failedCorrection) open @endif>
                    <summary style="cursor: pointer; font-weight: bold;">
                        Record a correction
                    </summary>

                    <p class="muted">
                        Identify the incorrect information and its correct
                        value. If correcting an earlier correction,
                        identify its correction number.
                        The original entry remains visible.
                    </p>

                    <form method="POST"
                          action="{{ route('health.immunizations.corrections.store', [
                              'healthPatient' => $patient,
                              'immunization' => $item,
                          ]) }}">
                        @csrf

                        <input type="hidden"
                               name="immunization_id"
                               value="{{ $item->id }}">

                        <input type="hidden"
                               name="correction_token"
                               value="{{ $failedCorrection
                                   ? old('correction_token')
                                   : (string) \Illuminate\Support\Str::uuid() }}">

                        <div style="margin-top: 18px;">
                            <label for="reason-{{ $item->id }}">
                                Reason for correction *
                            </label>

                            <textarea id="reason-{{ $item->id }}"
                                      name="reason"
                                      rows="3"
                                      minlength="10"
                                      maxlength="2000"
                                      required
                                      style="width: 100%; padding: 12px; font: inherit;">{{ $failedCorrection ? old('reason') : '' }}</textarea>
                        </div>

                        <div style="margin-top: 18px;">
                            <label for="correction-{{ $item->id }}">
                                Corrected information *
                            </label>

                            <textarea id="correction-{{ $item->id }}"
                                      name="correction"
                                      rows="4"
                                      minlength="10"
                                      maxlength="5000"
                                      required
                                      style="width: 100%; padding: 12px; font: inherit;">{{ $failedCorrection ? old('correction') : '' }}</textarea>
                        </div>

                        <div style="margin-top: 18px;">
                            <label for="correction-source-{{ $item->id }}">
                                Supporting source *
                            </label>

                            <textarea id="correction-source-{{ $item->id }}"
                                      name="correction_source"
                                      rows="3"
                                      minlength="10"
                                      maxlength="2000"
                                      required
                                      style="width: 100%; padding: 12px; font: inherit;">{{ $failedCorrection ? old('correction_source') : '' }}</textarea>
                        </div>

                        <label style="display: flex; gap: 10px; margin-top: 18px;">
                            <input type="checkbox"
                                   name="correction_confirmed"
                                   value="1"
                                   required
                                   style="width: auto;">

                            <span>
                                I reviewed the original entry and existing
                                corrections and verified this correction
                                against its supporting source.
                            </span>
                        </label>

                        <button type="submit">Save correction</button>
                    </form>
                </details>
            </article>
        @empty
            <p class="empty">
                No immunization records have been encoded.
                This does not establish that the patient has received
                no vaccines.
            </p>
        @endforelse

        <div class="pagination">
            <span class="muted">
                Showing {{ $immunizations->firstItem() ?? 0 }}
                to {{ $immunizations->lastItem() ?? 0 }}
                of {{ $immunizations->total() }} entries
            </span>

            @if ($immunizations->hasPages())
                <nav class="pagination-links"
                     aria-label="Immunization records pages">
                    @if ($immunizations->previousPageUrl())
                        <a href="{{ $immunizations->previousPageUrl() }}">
                            Previous
                        </a>
                    @endif

                    <span>
                        {{ $immunizations->currentPage() }}
                        / {{ $immunizations->lastPage() }}
                    </span>

                    @if ($immunizations->nextPageUrl())
                        <a href="{{ $immunizations->nextPageUrl() }}">
                            Next
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection