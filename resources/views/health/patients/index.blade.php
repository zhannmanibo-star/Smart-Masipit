@extends('layouts.app')

@section('title', 'Patient Registry')
@section('description', 'Access resident-linked patient records for authorized health services.')

@section('actions')
    <div class="row-actions">
        <a class="button secondary" href="{{ route('dashboard') }}">
            Dashboard
        </a>

        @can('manage-health-records')
            <a class="button" href="{{ route('health.patients.create') }}">
                + Register patient
            </a>
        @endcan

        @can('view-health-access-logs')
    <a class="button secondary"
       href="{{ route('health.access-history.index') }}">
        Access history
    </a>
@endcan
    </div>
@endsection

@section('content')
    <section class="card">
        <form method="GET"
              action="{{ route('health.patients.index') }}"
              class="toolbar"
              role="search">
            <div class="search-field">
                <label for="search">Find a patient</label>
                <input id="search" name="search" type="search"
                       value="{{ $search }}" maxlength="100"
                       placeholder="Name or resident reference">
            </div>

            <button type="submit">Search</button>
            <a class="button secondary" href="{{ route('health.patients.index') }}">
                Clear
            </a>
        </form>
    </section>

    <section class="card">
        <h2>Patient records ({{ $patients->total() }})</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Patient</th>
                        <th scope="col">Birth date</th>
                        <th scope="col">Registry status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($patients as $patient)
                        @php
                            $resident = $patient->resident;
                        @endphp
                        <tr>
                            <td>
                                @if ($resident)
                                    <strong>
                                        {{ $resident->last_name }},
                                        {{ $resident->first_name }}
                                        {{ $resident->middle_name }}
                                    </strong>

                                    <div class="reference muted">
                                        {{ $resident->resident_number }}
                                    </div>
                                @else
                                    Resident record unavailable
                                @endif
                            </td>

                            <td>
                                {{ $resident?->birth_date?->format('M d, Y') ?? 'Not recorded' }}
                            </td>

                            <td>
                                @if (! $resident)
                                    Unavailable
                                @else
                                    @if ($resident->isDeceased())
                                        <div><strong>Deceased</strong></div>
                                    @endif

                                    @if ($resident->trashed())
                                        <div>Archived</div>
                                    @elseif (! $resident->isDeceased())
                                        Active
                                    @endif
                                @endif
                            </td>

                            <td>
                                <a class="button secondary"
                                   href="{{ route('health.patients.show', $patient) }}">
                                    Open patient
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">
                                No patients match your search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('health.patients._pagination', [
            'records' => $patients,
            'label' => 'Patient registry pages',
        ])
    </section>

@endsection