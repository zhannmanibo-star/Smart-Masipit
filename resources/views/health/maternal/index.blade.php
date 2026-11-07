@extends('layouts.app')

@section('title', 'Maternal Health Records')
@section('description', 'Pregnancy records and antepartum visits.')

@section('actions')
    <a class="button secondary"
       href="{{ route('health.patients.show', $patient) }}">
        Back to patient
    </a>
@endsection

@section('content')
    @if (session('success'))
        <section class="card" role="status">
            {{ session('success') }}
        </section>
    @endif

    @if ($errors->any())
        <section class="card" role="alert">
            <strong>Please review:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card">
        <h2>
            {{ $resident?->last_name }},
            {{ $resident?->first_name }}
            {{ $resident?->middle_name }}
        </h2>

        <p>
            Create one maternal record for each pregnancy.
            Open an existing record to add follow-up visits.
        </p>

        <p class="muted">
            The printable form is a draft pending review against the
            official City Health template.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Record date</th>
                        <th>LMP</th>
                        <th>Recorded by</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $item)
                        <tr>
                            <td>{{ $item->reference_number }}</td>
                            <td>{{ $item->record_date->format('M d, Y') }}</td>
                            <td>{{ $item->details['lmp'] ?? 'Not recorded' }}</td>
                            <td>{{ $item->recordedBy?->name }}</td>
                            <td>
                                <a class="button secondary"
                                   href="{{ route('health.maternal.show', [$patient, $item]) }}">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No maternal records yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('health.patients._pagination', [
            'records' => $records,
            'label' => 'Maternal record pages',
        ])
    </section>

    @can('manage-health-records')
        @if (
    $resident
    && ! $resident->trashed()
    && $resident->sex === 'Female'
)
            <section class="card">
                <details @if ($errors->any()) open @endif>
                    <summary style="cursor: pointer; font-weight: bold;">
                        Register a pregnancy record
                    </summary>

                    <p>
                        Review the existing records first.
                        Leave unavailable clinical details blank.
                        A blank answer is not treated as “No.”
                    </p>

                    @if ($resident->isDeceased())
                        <p>
                            This resident has a recorded death.
                            Only historical records dated on or before
                            {{ $resident->date_of_death->format('M d, Y') }}
                            may be entered.
                        </p>
                    @endif

                    <form method="POST"
                          action="{{ route('health.maternal.store', $patient) }}">
                        @csrf

                        <input type="hidden" name="submission_token"
                               value="{{ old('submission_token', $submissionToken) }}">

                        <label for="record_date">Record date *</label>
                        <input id="record_date" name="record_date" type="date"
                               value="{{ old('record_date', now('Asia/Manila')->toDateString()) }}"
                               max="{{ now('Asia/Manila')->toDateString() }}"
                               required>

                        @include('health.maternal._fields', [
                            'sections' => \App\Support\MaternalFields::RECORD,
                        ])

                        <label style="display: flex; gap: 10px; margin: 20px 0;">
                            <input type="checkbox" name="details_confirmed"
                                   value="1" required style="width: auto;">
                            <span>
                                I verified the patient and reviewed these entries
                                against the interview or available source record.
                            </span>
                        </label>

                        <button type="submit">Save maternal record</button>
                    </form>
                </details>
            </section>
        @else
           <section class="card">
    New maternal records require an active resident record
    with sex recorded as Female. Refer incorrect registry
    information to authorized registry staff.
</section>
        @endif
    @endcan
@endsection