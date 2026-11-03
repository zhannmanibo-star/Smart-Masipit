@extends('layouts.app')

@section('title', 'Health History')
@section('description', 'Review recorded health information, its sources, and previous versions.')

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
            Birth date:
            {{ $resident?->birth_date?->format('M d, Y') ?? 'Unavailable' }}
        </p>

        @if ($resident?->isDeceased())
            <p class="muted">
                Recorded date of death:
                {{ $resident->date_of_death->format('M d, Y') }}.
                Entries on this page maintain historical information
                and its corrections.
            </p>
        @endif

        @if (! $resident || $resident->trashed())
            <p class="muted">
                This resident is archived or unavailable.
                Health history is retained for record review and correction.
            </p>
        @endif

        <p class="muted">
            “None reported” means the identified source reported none.
            It does not mean that an unassessed or blank record is negative.
        </p>

        @if ($errors->any())
            <div class="errors" role="alert">
                <strong>Please correct the following:</strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>

                @if ($errors->has('expected_version'))
                    <a href="{{ route('health.history.index', $patient) }}">
                        Reload and review the latest versions
                    </a>
                @endif
            </div>
        @endif
    </section>

    @foreach ($categories as $category => $label)
        @php
            $current = $currentEntries[$category] ?? null;
            $failedForm = $errors->any()
                && old('category') === $category;
        @endphp

        <section class="card">
            <h2>{{ $label }}</h2>

            @if ($current)
                <p class="muted">
                    Latest recorded version for this category.
                    Check the source date when reviewing this information.
                </p>

                @include('health.history._entry', [
                    'entry' => $current,
                ])
            @else
                <p>
                    <strong>Not yet assessed / no entry recorded.</strong>
                </p>
            @endif

            <details @if ($failedForm) open @endif>
                <summary style="cursor: pointer; font-weight: bold;">
                    {{ $current ? 'Record an updated version' : 'Record health history' }}
                </summary>

                <p class="muted">
                    Each new version should contain the complete information
                    you intend to record for this category.
                    Explain any correction or change.
                    Previous versions remain available below.
                </p>

                <form method="POST"
                      action="{{ route('health.history.store', $patient) }}">
                    @csrf

                    <input type="hidden"
                           name="category"
                           value="{{ $category }}">

                    <input type="hidden"
                           name="expected_version"
                           value="{{ $failedForm
                               ? old('expected_version')
                               : ($current?->version ?? 0) }}">

                    <input type="hidden"
                           name="submission_token"
                           value="{{ $failedForm
                               ? old('submission_token')
                               : (string) \Illuminate\Support\Str::uuid() }}">

                    <div style="margin-top: 18px;">
                        <label for="status-{{ $category }}">
                            Information status *
                        </label>

                        <select id="status-{{ $category }}"
                                name="finding_status"
                                required>
                            <option value="">Select status</option>

                            @foreach ($statuses as $value => $statusLabel)
                                <option value="{{ $value }}"
                                    @selected(
                                        $failedForm
                                        && old('finding_status') === $value
                                    )>
                                    {{ $statusLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div style="margin-top: 18px;">
                        <label for="details-{{ $category }}">
                            Details
                        </label>

                        <textarea id="details-{{ $category }}"
                                  name="details"
                                  rows="5"
                                  maxlength="10000"
                                  style="width: 100%; padding: 12px; font: inherit;">{{ $failedForm ? old('details') : '' }}</textarea>

                        <p class="muted">
                            Required for “Information reported or documented.”
                            Leave blank for the other statuses.
                        </p>

                        @if ($category === 'allergies')
                            <p class="muted">
                                Record the reported substance and reaction
                                when available. State when the reaction
                                or other details are unknown.
                            </p>
                        @elseif ($category === 'conditions')
                            <p class="muted">
                                Record conditions identified by the source.
                                Distinguish patient-reported information
                                from information in an existing health document.
                            </p>
                        @else
                            <p class="muted">
                                Record relevant past illnesses, operations,
                                or hospitalizations and known dates.
                                Do not invent missing dates or details.
                            </p>
                        @endif
                    </div>

                    <div style="margin-top: 18px;">
                        <label for="source-{{ $category }}">
                            Information source *
                        </label>

                        <select id="source-{{ $category }}"
                                name="information_source"
                                required>
                            <option value="">Select source</option>

                            @foreach ($sources as $value => $sourceLabel)
                                <option value="{{ $value }}"
                                    @selected(
                                        $failedForm
                                        && old('information_source') === $value
                                    )>
                                    {{ $sourceLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div style="margin-top: 18px;">
                        <label for="source-details-{{ $category }}">
                            Source details *
                        </label>

                        <textarea id="source-details-{{ $category }}"
                                  name="source_details"
                                  rows="3"
                                  minlength="5"
                                  maxlength="2000"
                                  required
                                  style="width: 100%; padding: 12px; font: inherit;">{{ $failedForm ? old('source_details') : '' }}</textarea>

                        <p class="muted">
                            Identify the interview, representative and
                            relationship, health personnel, or document
                            used as the source.
                        </p>
                    </div>

                    <div style="margin-top: 18px;">
                        <label for="source-date-{{ $category }}">
                            Interview / source document date *
                        </label>

                        <input id="source-date-{{ $category }}"
                               name="source_date"
                               type="date"
                               max="{{ now('Asia/Manila')->toDateString() }}"
                               value="{{ $failedForm ? old('source_date') : '' }}"
                               required>

                        <p class="muted">
                            This is the source date, not the date an illness began.
                            The encoding time is recorded automatically.
                        </p>
                    </div>

                    <div style="margin-top: 18px;">
                        <label for="reason-{{ $category }}">
                            Reason for this entry *
                        </label>

                        <textarea id="reason-{{ $category }}"
                                  name="change_reason"
                                  rows="3"
                                  minlength="10"
                                  maxlength="2000"
                                  required
                                  style="width: 100%; padding: 12px; font: inherit;">{{ $failedForm ? old('change_reason') : '' }}</textarea>

                        <p class="muted">
                            Explain whether this is initial documentation,
                            newly received information, or a correction.
                            For corrections, identify what changed and why.
                        </p>
                    </div>

                    <label style="display: flex; gap: 10px; margin-top: 18px;">
                        <input type="checkbox"
                               name="confirmed"
                               value="1"
                               required
                               style="width: auto;">

                        <span>
                            I verified the patient, reviewed the previous entry
                            where available, and identified the information source.
                        </span>
                    </label>

                    <button type="submit">
                        Save new version
                    </button>
                </form>
            </details>
        </section>
    @endforeach

    <section class="card">
        <h2>
            All recorded versions
            <span class="muted">({{ $entries->total() }})</span>
        </h2>

        <p class="muted">
            Listed by encoding order, newest first.
            Earlier versions are retained and may contain information
            corrected in a later version.
        </p>

        @forelse ($entries as $entry)
            <details style="padding: 16px 0; border-bottom: 1px solid #d4e2da;">
                <summary style="cursor: pointer;">
                    {{ $categories[$entry->category] ?? $entry->category }}
                    · Version {{ $entry->version }}

                    @if (
                        $entry->version
                        === ($currentEntries[$entry->category]?->version ?? 0)
                    )
                        · Latest recorded
                    @else
                        · Previous version
                    @endif
                </summary>

                @include('health.history._entry', [
                    'entry' => $entry,
                ])
            </details>
        @empty
            <p class="empty">No health history entries recorded.</p>
        @endforelse

        <div class="pagination">
            <span class="muted">
                Showing {{ $entries->firstItem() ?? 0 }}
                to {{ $entries->lastItem() ?? 0 }}
                of {{ $entries->total() }} entries
            </span>

            @if ($entries->hasPages())
                <nav class="pagination-links"
                     aria-label="Health history pages">
                    @if ($entries->previousPageUrl())
                        <a href="{{ $entries->previousPageUrl() }}">
                            Previous
                        </a>
                    @endif

                    <span>
                        {{ $entries->currentPage() }}
                        / {{ $entries->lastPage() }}
                    </span>

                    @if ($entries->nextPageUrl())
                        <a href="{{ $entries->nextPageUrl() }}">
                            Next
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection