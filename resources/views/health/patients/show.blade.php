@extends('layouts.app')

@section('title', 'Patient Profile')
@section('description', 'Resident-linked patient details and access history.')

@section('actions')
    <a class="button secondary" href="{{ route('health.patients.index') }}">
        Back to patients
    </a>
@endsection

@section('content')
    <section class="card">
        @if ($resident)
            <h2>
                {{ $resident->last_name }},
                {{ $resident->first_name }}
                {{ $resident->middle_name }}
            </h2>

            <p class="reference">{{ $resident->resident_number }}</p>

            @if ($resident->isDeceased())
                <p role="status">
                    <strong>Deceased</strong>
                    — {{ $resident->date_of_death->format('M d, Y') }}.
                    Existing patient information is retained for historical reference.
                </p>
            @endif

            @if ($resident->trashed())
                <p role="status">
                    <strong>Archived resident record.</strong>
                    Existing patient information remains available to authorized health staff.
                </p>
            @endif

            <dl>
                <dt>Birth date</dt>
                <dd>{{ $resident->birth_date->format('M d, Y') }}</dd>

                <dt>Sex</dt>
                <dd>{{ $resident->sex }}</dd>

                <dt>Sitio</dt>
                <dd>{{ $resident->sitio }}</dd>

                <dt>House / Street / Purok</dt>
                <dd>{{ $resident->address ?: 'Not recorded' }}</dd>

                <dt>Contact number</dt>
                <dd>{{ $resident->contact_number ?: 'Not recorded' }}</dd>
            </dl>

            <p class="muted">
                These details come from the resident registry.
                Refer identity or address corrections to authorized registry staff.
            </p>
        @else
            <h2>Resident record unavailable</h2>
            <p>Refer this record for review before further encoding.</p>
        @endif

        <p>
            <strong>Registered by:</strong>
            {{ $patient->registeredBy?->name ?? 'Not recorded' }}
        </p>

        <p>
            <strong>Patient registration date:</strong>
            {{ $patient->created_at->copy()
                ->timezone('Asia/Manila')
                ->format('M d, Y · h:i A') }}
            (Philippine time)
        </p>
    </section>

    <section class="card">
        <h2>Patient activity history</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Action</th>
                        <th scope="col">Health staff</th>
                        <th scope="col">Philippine time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activity as $entry)
                        <tr>
                            <td>
                                {{ [
                                    'patient_registered' => 'Patient registered',
                                    'patient_profile_viewed' => 'Patient profile opened',
                                ][$entry->action] ?? $entry->action }}
                            </td>

                            <td>{{ $entry->actor_name }}</td>

                            <td>
                                {{ \Carbon\Carbon::parse(
                                    $entry->occurred_at,
                                    config('app.timezone')
                                )->timezone('Asia/Manila')->format('M d, Y · h:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="empty">
                                No activity recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('health.patients._pagination', [
            'records' => $activity,
            'label' => 'Patient activity pages',
        ])
    </section>

    @can('manage-health-records')
    <section class="card">
        <h2>Consultation Records</h2>

        <p>
            Record consultations, review previous entries,
            and document corrections or clarifications.
        </p>

        <a class="button"
           href="{{ route('health.consultations.index', $patient) }}">
            Open consultations
        </a>
    </section>
@endcan

@can('manage-health-records')
    <section class="card">
        <h2>Health History</h2>

        <p>
            Review allergies, reported conditions, and relevant past
            health history, including information sources and corrections.
        </p>

        <a class="button"
           href="{{ route('health.history.index', $patient) }}">
            Open health history
        </a>
    </section>
@endcan

@can('manage-health-records')
    <section class="card">
        <h2>Immunization Records</h2>

        <p>
            Record administered vaccines and review source records
            and correction history.
        </p>

        <a class="button"
           href="{{ route('health.immunizations.index', $patient) }}">
            Open immunization records
        </a>
    </section>
@endcan
@endsection