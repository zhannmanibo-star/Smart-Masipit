@extends('layouts.app')

@section('title', 'Register Patient')
@section('description', 'Link an existing resident to the patient registry.')

@section('actions')
    <a class="button secondary" href="{{ route('health.patients.index') }}">
        Back to patients
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>Find an existing resident</h2>

        <p>
            Verify the resident's identity before registering.
            Their name, birth date, and address will come from the resident registry.
        </p>

        <form method="GET"
              action="{{ route('health.patients.create') }}"
              class="toolbar"
              role="search">
            <div class="search-field">
                <label for="search">Search residents</label>
                <input id="search" name="search" type="search"
                       value="{{ $search }}" maxlength="100"
                       placeholder="Name or resident reference">
            </div>

            <button type="submit">Search</button>
            <a class="button secondary" href="{{ route('health.patients.create') }}">
                Clear
            </a>
        </form>

        <p class="muted">
            Residents already registered as patients, archived records,
            and records with a recorded death are excluded.
            If the resident is missing, refer them to the resident registry staff.
        </p>
    </section>

    <section class="card">
        <h2>Select resident</h2>

        <form method="POST" action="{{ route('health.patients.store') }}">
            @csrf

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Select</th>
                            <th scope="col">Resident</th>
                            <th scope="col">Birth date</th>
                            <th scope="col">Sitio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($residents as $resident)
                            <tr>
                                <td>
                                    <input type="radio"
                                           id="resident_{{ $resident->id }}"
                                           name="resident_id"
                                           value="{{ $resident->id }}"
                                           @checked((string) old('resident_id') === (string) $resident->id)
                                           style="width: auto;"
                                           required>
                                </td>

                                <td>
                                    <label for="resident_{{ $resident->id }}">
                                        {{ $resident->last_name }},
                                        {{ $resident->first_name }}
                                        {{ $resident->middle_name }}
                                    </label>

                                    <div class="reference muted">
                                        {{ $resident->resident_number }}
                                    </div>
                                </td>

                                <td>{{ $resident->birth_date->format('M d, Y') }}</td>
                                <td>{{ $resident->sitio }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty">
                                    No eligible residents match your search.
                                    Check the patient registry for an existing profile.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('health.patients._pagination', [
                'records' => $residents,
                'label' => 'Available resident pages',
            ])

            <label style="display: flex; gap: 10px; margin: 18px 0;">
                <input type="checkbox" name="identity_confirmed"
                       value="1" style="width: auto;" required>

                <span>
                    I verified that the selected resident is the patient
                    being registered for health services.
                </span>
            </label>

            <button type="submit" @disabled($residents->isEmpty())>
                Register patient
            </button>
        </form>
    </section>
@endsection