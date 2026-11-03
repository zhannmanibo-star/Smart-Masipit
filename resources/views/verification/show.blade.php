@extends('layouts.app')

@section('title', 'Review Resident Application')
@section('description', 'Verify the applicant’s identity and residency before making a decision.')

@section('actions')
    <a class="button secondary" href="{{ route('verification.index') }}">
        Back to applications
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>Application #{{ $application->id }}</h2>

        <p class="muted">
            Submitted information is not yet verified.
            Compare it against barangay records and the supporting
            information provided through your verification procedure.
        </p>

        <div class="table-wrap">
            <table>
                <tbody>
                    <tr>
                        <th scope="row">Applicant</th>
                        <td>
                            {{ $application->first_name }}
                            {{ $application->middle_name }}
                            {{ $application->last_name }}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Birth date</th>
                        <td>
                            {{ $application->birth_date->format('M d, Y') }}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sex</th>
                        <td>{{ $application->sex }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Sitio</th>
                        <td>{{ $application->sitio }}</td>
                    </tr>
                    <tr>
                        <th scope="row">House / Street / Purok</th>
                        <td>{{ $application->address ?: 'Not provided' }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Contact number</th>
                        <td>{{ $application->contact_number ?: 'Not provided' }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Email</th>
                        <td>{{ $application->user->email }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

   <section class="card">
    <h2>Resident record</h2>

    <p>
        Use the submitted application details to add a verified resident.
        The system will check for possible existing records first.
    </p>

    <div
        style="padding: 20px; margin: 20px 0;
               border: 1px solid #c6ddd2;
               border-radius: 8px; background: #eff7f2;"
    >
        <h3 style="margin-top: 0;">Add resident from this application</h3>

        <p class="muted">
            Personal and residence details shown above will be copied
            into the resident record. Verify them before continuing.
        </p>

        <form
            method="POST"
            action="{{ route('verification.create-resident', $application) }}"
            onsubmit="return confirm('Use this application to find or create a resident record? Account approval will remain a separate step.');"
        >
            @csrf

            <label style="margin: 18px 0; font-weight: 400;">
                <input
                    type="checkbox"
                    name="residency_confirmed"
                    value="1"
                    required
                >
                I have verified the applicant’s identity, residency
                in Barangay Masipit, and the details shown above.
            </label>

            <button type="submit">
                + Add resident from application
            </button>
        </form>
    </div>

    <h3>Already know the resident reference?</h3>

    <p class="muted">
        Load a specific existing record for comparison.
        Do not create a new record for a person already in the registry.
    </p>

    <form
        method="GET"
        action="{{ route('verification.show', $application) }}"
        class="toolbar"
    >
        <div class="search-field">
            <label for="resident_reference">Resident reference</label>

            <input
                id="resident_reference"
                name="resident_reference"
                value="{{ $reference }}"
                maxlength="36"
                placeholder="Paste the complete resident reference"
                required
            >
        </div>

        <button type="submit">Load record</button>
    </form>

    <p style="margin-bottom: 0;">
        <a
            href="{{ route('residents.index') }}"
            target="_blank"
            rel="noopener noreferrer"
        >
            Open Resident Registry in a new tab
        </a>
    </p>
</section>

    @if ($reference !== '' && ! $resident)
        <div class="alert error" role="alert">
            No active resident record matches this reference.
            Check the reference or review deleted records with an administrator.
        </div>
    @endif

    @if ($resident)
        <section class="card">
            <h2>Compare the records</h2>

            <p class="reference">{{ $resident->resident_number }}</p>

            @if ($alreadyLinked)
                <div class="alert error" role="alert">
                    This resident already has a linked account.
                    Approval using this record is blocked.
                </div>
            @endif

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Field</th>
                            <th scope="col">Applicant submitted</th>
                            <th scope="col">Resident Registry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">Name</th>
                            <td>
                                {{ $application->first_name }}
                                {{ $application->middle_name }}
                                {{ $application->last_name }}
                            </td>
                            <td>
                                {{ $resident->first_name }}
                                {{ $resident->middle_name }}
                                {{ $resident->last_name }}
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Birth date</th>
                            <td>
                                {{ $application->birth_date->format('M d, Y') }}
                            </td>
                            <td>
                                {{ $resident->birth_date->format('M d, Y') }}
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Sex</th>
                            <td>{{ $application->sex }}</td>
                            <td>{{ $resident->sex }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Sitio</th>
                            <td>{{ $application->sitio }}</td>
                            <td>{{ $resident->sitio }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Address details</th>
                            <td>{{ $application->address ?: 'Not provided' }}</td>
                            <td>{{ $resident->address ?: 'Not provided' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="muted">
                Loading a record does not verify identity.
                Resolve any discrepancies before approving.
                Approval will not overwrite the resident’s profile.
            </p>

            @if (! $alreadyLinked)
                <form
                    method="POST"
                    action="{{ route('verification.decide', $application) }}"
                    onsubmit="return confirm('Approve this application and link it to the resident record shown above?');"
                >
                    @csrf

                    <input type="hidden" name="decision" value="approved">
                    <input
                        type="hidden"
                        name="resident_reference"
                        value="{{ $resident->resident_number }}"
                    >

                    <div style="margin-top: 24px;">
                        <label for="approval_note">
                            Verification note *
                        </label>

                        <textarea
                            id="approval_note"
                            name="review_note"
                            rows="4"
                            minlength="10"
                            maxlength="2000"
                            required
                            style="width: 100%; padding: 12px; border: 1px solid #bac8c1; border-radius: 7px; font: inherit;"
                            placeholder="Describe how identity and residency were verified and explain any discrepancies."
                        >{{ old('decision') === 'approved' ? old('review_note') : '' }}</textarea>
                    </div>

                    <label style="margin: 18px 0; font-weight: 400;">
                        <input
                            type="checkbox"
                            name="review_confirmed"
                            value="1"
                            required
                        >
                        I verified that the applicant is the person in
                        this resident record and is a resident of Barangay Masipit.
                    </label>

                    <button type="submit">Approve resident account</button>
                </form>
            @endif
        </section>
    @endif

    <section class="card">
        <h2>Reject application</h2>

        <p class="muted">
            Use this when the application cannot be approved.
            The reason below will be visible to the applicant.
        </p>

        <form
            method="POST"
            action="{{ route('verification.decide', $application) }}"
            onsubmit="return confirm('Reject this application and make the stated reason visible to the applicant?');"
        >
            @csrf

            <input type="hidden" name="decision" value="rejected">

            <label for="rejection_note">Reason for rejection *</label>

            <textarea
                id="rejection_note"
                name="review_note"
                rows="4"
                minlength="10"
                maxlength="2000"
                required
                style="width: 100%; padding: 12px; border: 1px solid #bac8c1; border-radius: 7px; font: inherit;"
                placeholder="Explain clearly why the application was not approved."
            >{{ old('decision') === 'rejected' ? old('review_note') : '' }}</textarea>

            <label style="margin: 18px 0; font-weight: 400;">
                <input
                    type="checkbox"
                    name="review_confirmed"
                    value="1"
                    required
                >
                I reviewed this application and confirm the rejection reason.
            </label>

            <button type="submit" class="danger">
                Reject application
            </button>
        </form>
    </section>
@endsection