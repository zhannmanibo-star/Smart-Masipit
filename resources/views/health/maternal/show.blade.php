@extends('layouts.app')

@section('title', 'Maternal Record')
@section('description', 'Pregnancy details, antepartum visits, and correction notes.')

@section('actions')
    <a class="button secondary"
       href="{{ route('health.maternal.index', $patient) }}">
        Back to maternal records
    </a>

    <a class="button"
       href="{{ route('health.maternal.print', [$patient, $record]) }}">
        Print preview — draft
    </a>
@endsection

@section('content')
    @php
        $snapshot = $record->patient_snapshot;
        $details = $record->details;
    @endphp

    @if (session('success'))
        <section class="card" role="status">
            {{ session('success') }}
        </section>
    @endif

    @if ($errors->any())
        <section class="card" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card">
        <h2>
            {{ $snapshot['last_name'] }},
            {{ $snapshot['first_name'] }}
            {{ $snapshot['middle_name'] ?? '' }}
        </h2>

        <p><strong>Reference:</strong> {{ $record->reference_number }}</p>
        <p><strong>Record date:</strong> {{ $record->record_date->format('M d, Y') }}</p>
        <p><strong>Address at registration:</strong> {{ $snapshot['address'] }}</p>
        <p><strong>Recorded by:</strong> {{ $record->recordedBy?->name }}</p>

        <p class="muted">
            Original information is retained.
            Corrections are recorded separately below and included in the printout.
        </p>

        @foreach (\App\Support\MaternalFields::RECORD as $section => $fields)
            <details style="margin: 16px 0;">
                <summary style="font-weight: bold; cursor: pointer;">
                    {{ $section }}
                </summary>

                <dl>
                    @foreach ($fields as $key => [$label, $type])
                        <dt style="font-weight: bold; margin-top: 12px;">{{ $label }}</dt>
                        <dd style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ ($details[$key] ?? null) !== null && ($details[$key] ?? '') !== '' ? $details[$key] : 'Not recorded' }}</dd>
                    @endforeach
                </dl>
            </details>
        @endforeach
    </section>

    <section class="card">
        <h2>Antepartum visits</h2>

        @forelse ($visits as $visit)
            <article style="padding: 16px 0; border-bottom: 1px solid #ccd8d2;">
                <h3>
                    Visit #{{ $visit->id }}
                    — {{ $visit->visit_date->format('M d, Y') }}
                </h3>

                <p>Recorded by: {{ $visit->recordedBy?->name }}</p>

                <dl>
                    @foreach (\App\Support\MaternalFields::VISIT['Antepartum visit'] as $key => [$label, $type])
                        <dt style="font-weight: bold; margin-top: 8px;">{{ $label }}</dt>
                        <dd style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $visit->details[$key] ?? 'Not recorded' }}</dd>
                    @endforeach
                </dl>
            </article>
        @empty
            <p>No antepartum visits recorded.</p>
        @endforelse
    </section>

    @can('manage-health-records')
       @if (
    $resident
    && ! $resident->trashed()
    && $resident->sex === 'Female'
)
            <section class="card">
                <h2>Add antepartum visit</h2>

                <p>
                    Enter observations as recorded by the health professional.
                    Include units. Leave unmeasured values blank.
                </p>

                <form method="POST"
                      action="{{ route('health.maternal.visits.store', [$patient, $record]) }}">
                    @csrf

                    <input type="hidden" name="submission_token"
                           value="{{ old('submission_token', $visitToken) }}">

                    <label for="visit_date">Visit date *</label>
                    <input id="visit_date" name="visit_date" type="date"
                           value="{{ old('visit_date', now('Asia/Manila')->toDateString()) }}"
                           max="{{ now('Asia/Manila')->toDateString() }}"
                           required>

                    @include('health.maternal._fields', [
                        'sections' => \App\Support\MaternalFields::VISIT,
                    ])

                    <label style="display: flex; gap: 10px; margin: 20px 0;">
                        <input type="checkbox" name="details_confirmed"
                               value="1" required style="width: auto;">
                        <span>
                            I checked the patient, pregnancy record,
                            visit date, and observations.
                        </span>
                    </label>

                    <button type="submit">Save visit</button>
                </form>
            </section>
        @endif
    @endcan

    <section class="card">
        <h2>Corrections and clarifications</h2>

        @forelse ($addendums as $entry)
            <article style="padding: 14px 0; border-bottom: 1px solid #ccd8d2;">
                <p>
                    <strong>{{ $entry->actor_name }}</strong>
                    —
                    {{ \Carbon\Carbon::parse($entry->created_at, config('app.timezone'))
                        ->timezone('Asia/Manila')->format('M d, Y · h:i A') }}
                </p>

                <p><strong>Reason:</strong> {{ $entry->reason }}</p>
                <div style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $entry->addendum }}</div>
            </article>
        @empty
            <p>No correction notes.</p>
        @endforelse

        @can('manage-health-records')
            <form method="POST"
                  action="{{ route('health.maternal.addendums.store', [$patient, $record]) }}"
                  style="margin-top: 24px;">
                @csrf

                <input type="hidden" name="submission_token"
                       value="{{ old('submission_token', $addendumToken) }}">

                <p>
                    Identify the field or visit number, original value,
                    corrected information, and supporting source.
                    Original entries will remain visible.
                </p>

                <label for="reason">Reason *</label>
                <textarea id="reason" name="reason" rows="3"
                          minlength="10" maxlength="1000" required
                          style="width: 100%; font: inherit;">{{ old('reason') }}</textarea>

                <label for="addendum">Correction / clarification *</label>
                <textarea id="addendum" name="addendum" rows="5"
                          minlength="10" maxlength="3000" required
                          style="width: 100%; font: inherit;">{{ old('addendum') }}</textarea>

                <label style="display: flex; gap: 10px; margin: 20px 0;">
                    <input type="checkbox" name="details_confirmed"
                           value="1" required style="width: auto;">
                    <span>I verified this correction or clarification.</span>
                </label>

                <button type="submit">Save correction note</button>
            </form>
        @endcan
    </section>
@endsection