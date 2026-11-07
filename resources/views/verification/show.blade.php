@extends('layouts.app')

@section('title', 'Review Resident Application')
@section('description', 'Review identity evidence and compare the applicant with barangay records.')

@section('actions')
    <a class="button secondary" href="{{ route('verification.index') }}">
        Back to applications
    </a>
@endsection

@section('content')
    @php
        $hasEvidence = $application->hasIdentityEvidence();

        $applicantName = implode(' ', array_filter([
            $application->first_name,
            $application->middle_name,
            $application->last_name,
        ]));

        $residentName = $resident
            ? implode(' ', array_filter([
                $resident->first_name,
                $resident->middle_name,
                $resident->last_name,
            ]))
            : null;
    @endphp

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
        <h2>Application #{{ $application->id }}</h2>

        <p class="muted">
            Submitted information is not yet verified.
            Compare it against the ID, selfie, and barangay records.
        </p>

        <div class="table-wrap">
            <table>
                <tbody>
                    <tr>
                        <th scope="row">Applicant</th>
                        <td>{{ $applicantName }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Birth date</th>
                        <td>{{ $application->birth_date->format('M d, Y') }}</td>
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
        <h2>Identity evidence</h2>

        @if ($hasEvidence)
            <p class="muted">
                Submitted:
                {{ $application->identity_submitted_at
                    ->copy()
                    ->timezone('Asia/Manila')
                    ->format('M d, Y · h:i A') }}
                (Philippine time)
            </p>

            <p>
                Compare the ID photo with the selfie and check the
                applicant's details. Camera capture is not automated
                identity or liveness verification.
            </p>

            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 24px;"
            >
               @foreach ([
    'id' => 'ID front',
    'id-back' => 'ID back',
    'selfie' => 'Camera selfie',
] as $kind => $label)
                    @php
                        $imageUrl = route('verification.identity.show', [
                            'application' => $application->id,
                            'kind' => $kind,
                            'version' => $application->identity_evidence_version,
                        ]);
                    @endphp

                    <div>
                        <h3>{{ $label }}</h3>

                        <a
                            href="{{ $imageUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <img
                                src="{{ $imageUrl }}"
                                alt="{{ $label }} for this application"
                                style="display: block; width: 100%; max-height: 420px; object-fit: contain; border: 1px solid #dfe5e9; border-radius: 8px;"
                            >
                        </a>

                        <p>
                            <a
                                href="{{ $imageUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Open full image
                            </a>
                        </p>
                    </div>
                @endforeach
            </div>

            <p class="muted">
                If either image cannot be opened, do not confirm its review.
                Resolve the missing or unreadable image first.
            </p>
        @else
            <p role="alert">
              The applicant must submit the front and back of their valid ID
and a camera selfie. They can sign in and select
“Submit ID and selfie” on their account-status page.
            </p>
        @endif
    </section>

    <section class="card">
        <h2>Resident record</h2>

        <p>
            Use the application details to find or add a resident.
            The system checks for possible existing records first.
        </p>

        @if ($hasEvidence)
            <form
                method="POST"
                action="{{ route('verification.create-resident', $application) }}"
                onsubmit="return confirm('Find or create a resident record from this application? Approval remains a separate step.');"
            >
                @csrf

                <input
                    type="hidden"
                    name="identity_evidence_version"
                    value="{{ $application->identity_evidence_version }}"
                >

                <label style="display: block; margin: 18px 0; font-weight: 400;">
                    <input
                        type="checkbox"
                        name="identity_review_confirmed"
                        value="1"
                        required
                    >
                   I inspected the front and back of the applicant's ID,
checked its details, and compared the ID photo with the selfie.
                </label>

                <label style="display: block; margin: 18px 0; font-weight: 400;">
                    <input
                        type="checkbox"
                        name="residency_confirmed"
                        value="1"
                        required
                    >
                    I verified the applicant's identity, residency
                    in Barangay Masipit, and submitted details.
                </label>

                <button type="submit">+ Add resident from application</button>
            </form>
        @else
            <p class="muted">
                Adding a resident from this application is unavailable
                until the identity images are submitted.
            </p>
        @endif

        <h3>Already know the resident reference?</h3>

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

        <p>
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
        <section class="card" role="alert">
            No active resident record matches this reference.
            Check the reference or review deleted records with an administrator.
        </section>
    @endif

    @if ($resident)
        <section class="card">
            <h2>Compare the records</h2>

            <p class="reference">{{ $resident->resident_number }}</p>

            @if ($alreadyLinked)
                <p role="alert">
                    This resident already has a linked account.
                    Approval using this record is blocked.
                </p>
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
                            <td>{{ $applicantName }}</td>
                            <td>{{ $residentName }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Birth date</th>
                            <td>{{ $application->birth_date->format('M d, Y') }}</td>
                            <td>{{ $resident->birth_date->format('M d, Y') }}</td>
                        </tr>
                        @foreach ([
                            'sex' => 'Sex',
                            'sitio' => 'Sitio',
                            'address' => 'Address details',
                        ] as $field => $label)
                            <tr>
                                <th scope="row">{{ $label }}</th>
                                <td>{{ $application->{$field} ?: 'Not provided' }}</td>
                                <td>{{ $resident->{$field} ?: 'Not provided' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="muted">
                Resolve discrepancies before approving.
                Approval does not overwrite the resident's profile.
            </p>

            @if (! $alreadyLinked && $hasEvidence)
                <form
                    method="POST"
                    action="{{ route('verification.decide', $application) }}"
                    onsubmit="return confirm('Approve this account and link it to the resident shown above?');"
                >
                    @csrf

                    <input type="hidden" name="decision" value="approved">

                    <input
                        type="hidden"
                        name="resident_reference"
                        value="{{ $resident->resident_number }}"
                    >

                    <input
                        type="hidden"
                        name="identity_evidence_version"
                        value="{{ $application->identity_evidence_version }}"
                    >

                    <label for="approval_note">Verification note *</label>

                    <textarea
                        id="approval_note"
                        name="review_note"
                        rows="4"
                        minlength="10"
                        maxlength="2000"
                        required
                        style="width: 100%; padding: 12px; font: inherit;"
                        placeholder="Describe how identity and residency were verified. Do not copy ID numbers into this note."
                    >{{ old('decision') === 'approved' ? old('review_note') : '' }}</textarea>

                    <label style="display: block; margin: 18px 0; font-weight: 400;">
                        <input
                            type="checkbox"
                            name="identity_review_confirmed"
                            value="1"
                            required
                        >
                        I inspected the valid ID and selfie, compared the
                        photographs, and verified the applicant's identity.
                    </label>

                    <label style="display: block; margin: 18px 0; font-weight: 400;">
                        <input
                            type="checkbox"
                            name="review_confirmed"
                            value="1"
                            required
                        >
                        I verified that the applicant is the person in this
                        resident record and resides in Barangay Masipit.
                    </label>

                    <button type="submit">Approve resident account</button>
                </form>
            @elseif (! $hasEvidence)
                <p role="alert">
                    Approval is unavailable until the required
                    identity images are submitted and reviewed.
                </p>
            @endif
        </section>
    @endif

    <section class="card">
        <h2>Reject application</h2>

        <p class="muted">
            The rejection reason will be visible to the applicant.
        </p>

        <form
            method="POST"
            action="{{ route('verification.decide', $application) }}"
            onsubmit="return confirm('Reject this application?');"
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
                style="width: 100%; padding: 12px; font: inherit;"
                placeholder="Explain clearly why the application was not approved."
            >{{ old('decision') === 'rejected' ? old('review_note') : '' }}</textarea>

            <label style="display: block; margin: 18px 0; font-weight: 400;">
                <input
                    type="checkbox"
                    name="review_confirmed"
                    value="1"
                    required
                >
                I reviewed this application and confirm the rejection reason.
            </label>

            <button type="submit" class="danger">Reject application</button>
        </form>
    </section>
@endsection