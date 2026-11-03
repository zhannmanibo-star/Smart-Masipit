@extends('layouts.app')

@section('title', 'Walk-in Document Request')
@section('description', 'Select an existing resident and encode their document request.')

@section('actions')
    <a class="button secondary" href="{{ route('staff.documents.index') }}">
        Back to requests
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>1. Find the resident</h2>

        <form
            method="GET"
            action="{{ route('staff.documents.walk-in.create') }}"
            class="toolbar"
            role="search"
        >
            <div class="search-field">
                <label for="search">Search resident registry</label>

                <input
                    id="search"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Name, sitio, or resident reference"
                >
            </div>

            <button type="submit">Search</button>

            <a
                class="button secondary"
                href="{{ route('staff.documents.walk-in.create') }}"
            >
                Clear
            </a>
        </form>

        <p class="muted">
            Confirm the correct person using their birth date,
            sitio, and resident reference.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Resident</th>
                        <th scope="col">Birth date</th>
                        <th scope="col">Sitio</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($residents as $resident)
                        <tr>
                            <td>
                                <strong>
                                    {{ $resident->last_name }},
                                    {{ $resident->first_name }}
                                    {{ $resident->middle_name }}
                                </strong>

                                <div class="reference">
                                    {{ $resident->resident_number }}
                                </div>
                            </td>

                            <td>
                                {{ $resident->birth_date->format('M d, Y') }}
                            </td>

                            <td>{{ $resident->sitio }}</td>

                            <td>
                                <a
                                    class="button secondary"
                                    href="{{ route('staff.documents.walk-in.create', [
                                        'search' => $search,
                                        'resident' => $resident->id,
                                        'page' => $residents->currentPage(),
                                    ]) }}"
                                >
                                    Select resident
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">
                                No matching active residents.
                                If the person is not registered, complete
                                resident registration and verification first.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($residents->hasPages())
            <nav class="pagination" aria-label="Resident search pages">
                @if ($residents->previousPageUrl())
                    <a href="{{ $residents->previousPageUrl() }}">
                        Previous
                    </a>
                @endif

                <span>
                    Page {{ $residents->currentPage() }}
                    of {{ $residents->lastPage() }}
                </span>

                @if ($residents->nextPageUrl())
                    <a href="{{ $residents->nextPageUrl() }}">
                        Next
                    </a>
                @endif
            </nav>
        @endif
    </section>

    @if ($selectedResident)
        <section class="card">
            <h2>2. Confirm the selected resident</h2>

            <dl>
                <dt>Full name</dt>
                <dd>
                    {{ $selectedResident->first_name }}
                    {{ $selectedResident->middle_name }}
                    {{ $selectedResident->last_name }}
                </dd>

                <dt>Resident reference</dt>
                <dd>{{ $selectedResident->resident_number }}</dd>

                <dt>Birth date</dt>
                <dd>
                    {{ $selectedResident->birth_date->format('F d, Y') }}
                </dd>

                <dt>Sitio</dt>
                <dd>{{ $selectedResident->sitio }}</dd>

                <dt>House / Street / Purok</dt>
                <dd>{{ $selectedResident->address ?: 'Not recorded' }}</dd>
            </dl>
        </section>

        <section class="card">
            <h2>3. Encode the request</h2>

            <p class="muted">
                This will be recorded as a walk-in request under your
                staff account. The resident does not need an online account.
            </p>

            <form
                method="POST"
                action="{{ route('staff.documents.walk-in.store') }}"
            >
                @csrf

                <input
                    type="hidden"
                    name="resident_id"
                    value="{{ $selectedResident->id }}"
                >

                <input
                    type="hidden"
                    name="submission_token"
                    value="{{ old('submission_token', $submissionToken) }}"
                >

                <label for="document_type">Document type *</label>

                <select id="document_type" name="document_type" required>
                    <option value="">Select a document</option>

                    @foreach ($types as $value => $label)
                        <option
                            value="{{ $value }}"
                            @selected(old('document_type') === $value)
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <div style="margin-top: 18px;">
                    <label for="purpose">Purpose *</label>

                    <textarea
                        id="purpose"
                        name="purpose"
                        rows="4"
                        minlength="5"
                        maxlength="1000"
                        required
                        style="width: 100%; padding: 12px; font: inherit;"
                    >{{ old('purpose') }}</textarea>
                </div>

                <label style="display: flex; gap: 10px; margin-top: 18px;">
                    <input
                        type="checkbox"
                        name="identity_confirmed"
                        value="1"
                        style="width: auto;"
                        required
                    >

                    <span>
                        I verified the resident’s identity and confirmed
                        the document type and purpose with the applicant.
                    </span>
                </label>

                <button type="submit">Submit walk-in request</button>
            </form>
        </section>
    @endif
@endsection