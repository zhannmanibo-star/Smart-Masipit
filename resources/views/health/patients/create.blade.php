@extends('layouts.app')

@section('title', 'Register Patient')
@section('description', 'Register a patient using an existing or newly verified resident record. No online account is required.')

@section('actions')
    <a
        class="button secondary"
        href="{{ route('health.patients.index') }}"
    >
        Back to patients
    </a>
@endsection

@section('content')
    @if ($errors->any())
        <section class="card" role="alert">
            <h2>Please review the following</h2>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card">
        <h2>Find an existing resident</h2>

        <p>
            Search for the patient first. An existing resident can be
            registered as a patient even without an online account.
        </p>

        <form
            method="GET"
            action="{{ route('health.patients.create') }}"
            class="toolbar"
            role="search"
        >
            <div class="search-field">
                <label for="search">Search residents</label>

                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Name, sitio, or resident reference"
                >
            </div>

            <button type="submit">Search</button>

            <a
                class="button secondary"
                href="{{ route('health.patients.create') }}"
            >
                Clear
            </a>
        </form>

        <p class="muted">
            Residents already registered as patients, archived records,
            and records with a recorded death are excluded from this list.
        </p>

        <p>
            <a
                href="{{ route('health.patients.index', [
                    'search' => $search,
                ]) }}"
            >
                Check the patient registry for an existing profile
            </a>
        </p>
    </section>

    <section class="card">
        <h2>Select an existing resident</h2>

        <form
            method="POST"
            action="{{ route('health.patients.store') }}"
        >
            @csrf

            <input
                type="hidden"
                name="registration_mode"
                value="existing"
            >

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
                                    <input
                                        type="radio"
                                        id="resident_{{ $resident->id }}"
                                        name="resident_id"
                                        value="{{ $resident->id }}"
                                        @checked(
                                            old('registration_mode') === 'existing'
                                            && (string) old('resident_id')
                                                === (string) $resident->id
                                        )
                                        style="width: auto;"
                                        required
                                    >
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

                                <td>
                                    {{ $resident->birth_date->format('M d, Y') }}
                                </td>

                                <td>{{ $resident->sitio }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty">
                                    No eligible residents match your search.
                                    Check the patient registry before adding
                                    a new resident.
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
                <input
                    type="checkbox"
                    name="identity_confirmed"
                    value="1"
                    style="width: auto;"
                    required
                >

                <span>
                    I verified that the selected resident is the patient
                    being registered for health services.
                </span>
            </label>

            <button type="submit" @disabled($residents->isEmpty())>
                Register selected resident as patient
            </button>
        </form>
    </section>

    <section class="card">
        <h2>Patient has no resident record?</h2>

        <p>
            After checking both registries, Health Staff may add the
            resident and register the patient here.
            No email address, password, or online account is required.
        </p>

        <details @if (old('registration_mode') === 'new') open @endif>
            <summary
                style="cursor: pointer; font-weight: bold; color: #176b4c;"
            >
                + Add resident and register patient
            </summary>

            <p class="muted">
                This creates a shared resident registry record and a
                linked patient profile. Verify the person's identity
                and residency in Barangay Masipit before saving.
            </p>

            <form
                method="POST"
                action="{{ route('health.patients.store') }}"
                style="margin-top: 20px;"
            >
                @csrf

                <input
                    type="hidden"
                    name="registration_mode"
                    value="new"
                >

                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 18px;"
                >
                    <div>
                        <label for="new_first_name">First name *</label>

                        <input
                            id="new_first_name"
                            name="first_name"
                            type="text"
                            value="{{ old('first_name') }}"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div>
                        <label for="new_middle_name">Middle name</label>

                        <input
                            id="new_middle_name"
                            name="middle_name"
                            type="text"
                            value="{{ old('middle_name') }}"
                            maxlength="100"
                        >
                    </div>

                    <div>
                        <label for="new_last_name">Last name *</label>

                        <input
                            id="new_last_name"
                            name="last_name"
                            type="text"
                            value="{{ old('last_name') }}"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div>
                        <label for="new_birth_date">Birth date *</label>

                        <input
                            id="new_birth_date"
                            name="birth_date"
                            type="date"
                            value="{{ old('birth_date') }}"
                            max="{{ now('Asia/Manila')->toDateString() }}"
                            required
                        >
                    </div>

                    <div>
                        <label for="new_sex">Sex *</label>

                        <select id="new_sex" name="sex" required>
                            <option value="">Select</option>

                            <option
                                value="Male"
                                @selected(old('sex') === 'Male')
                            >
                                Male
                            </option>

                            <option
                                value="Female"
                                @selected(old('sex') === 'Female')
                            >
                                Female
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="new_contact_number">
                            Contact number
                        </label>

                        <input
                            id="new_contact_number"
                            name="contact_number"
                            type="tel"
                            value="{{ old('contact_number') }}"
                            maxlength="30"
                        >
                    </div>

                    <div>
                        <label for="new_sitio">Sitio *</label>

                        <input
                            id="new_sitio"
                            name="sitio"
                            type="text"
                            value="{{ old('sitio') }}"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div>
                        <label for="new_address">
                            House / Street / Purok *
                        </label>

                        <input
                            id="new_address"
                            name="address"
                            type="text"
                            value="{{ old('address') }}"
                            maxlength="255"
                            required
                        >
                    </div>
                </div>

                <p class="muted">
                    Barangay: Masipit, Calapan City, Oriental Mindoro.
                </p>

                <label style="display: flex; gap: 10px; margin: 20px 0;">
                    <input
                        type="checkbox"
                        name="new_resident_confirmed"
                        value="1"
                        style="width: auto;"
                        required
                    >

                    <span>
                        I verified this person's identity and residency
                        in Barangay Masipit, checked for existing resident
                        and patient records, and confirmed the details above.
                    </span>
                </label>

                <p class="muted">
                    Possible duplicate records will be held for review.
                    This form does not create or approve an online account.
                </p>

                <button type="submit">
                    Save resident and register patient
                </button>
            </form>
        </details>
    </section>
@endsection