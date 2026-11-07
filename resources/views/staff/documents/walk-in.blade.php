@extends('layouts.app')

@section('title', 'Walk-in Document Request')
@section('description', 'Encode a document request for a resident with or without an online account.')

@section('actions')
    <a
        class="button secondary"
        href="{{ route('staff.documents.index') }}"
    >
        Back to document requests
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

    @if (! $selectedResident)
        <section class="card">
            <h2>Find the applicant</h2>

            <p class="muted">
                Search the resident registry first.
                Residents can request documents through walk-in
                even if they do not have an online account.
            </p>

            <form
                method="GET"
                action="{{ route('staff.documents.walk-in.create') }}"
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
                    href="{{ route('staff.documents.walk-in.create') }}"
                >
                    Clear
                </a>
            </form>

            @can('manage-residents')
                <div style="margin: 20px 0;">
                    <p>
                        If the applicant has no existing resident record,
                        register them first.
                    </p>

                    <a
                        class="button"
                        href="{{ route('residents.create', [
                            'return_to' => 'walk_in',
                        ]) }}"
                    >
                        + Add resident
                    </a>

                    <p class="muted">
                        After saving, you will return here with the
                        new resident selected. No online account is required.
                    </p>
                </div>
            @endcan

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
                                    {{ $resident->last_name }},
                                    {{ $resident->first_name }}
                                    {{ $resident->middle_name }}

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
                                            'resident' => $resident->id,
                                            'search' => $search,
                                        ]) }}"
                                    >
                                        Select
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    No eligible residents found.
                                    Check the spelling or try another search.
                                    Archived residents and residents with a
                                    recorded death are not eligible for selection.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($residents->hasPages())
                <nav
                    class="pagination-links"
                    aria-label="Resident pages"
                >
                    @if ($residents->previousPageUrl())
                        <a href="{{ $residents->previousPageUrl() }}">
                            Previous
                        </a>
                    @endif

                    <span>
                        {{ $residents->currentPage() }}
                        / {{ $residents->lastPage() }}
                    </span>

                    @if ($residents->nextPageUrl())
                        <a href="{{ $residents->nextPageUrl() }}">
                            Next
                        </a>
                    @endif
                </nav>
            @endif
        </section>
    @else
        <section class="card">
            <h2>Selected applicant</h2>

            <p>
                <strong>
                    {{ $selectedResident->first_name }}
                    {{ $selectedResident->middle_name }}
                    {{ $selectedResident->last_name }}
                </strong>
            </p>

            <p>
                <strong>Reference:</strong>
                {{ $selectedResident->resident_number }}
            </p>

            <p>
                <strong>Birth date:</strong>
                {{ $selectedResident->birth_date->format('M d, Y') }}
            </p>

            <p>
                <strong>Sitio:</strong>
                {{ $selectedResident->sitio }}
            </p>

            <p>
                <strong>Address:</strong>
                {{ $selectedResident->address ?: 'Not recorded' }}
            </p>

            <p class="muted">
                An online account is not required for this walk-in request.
                Confirm the applicant's identity and resident details
                before submitting.
            </p>

            <a
                class="button secondary"
                href="{{ route('staff.documents.walk-in.create', [
                    'search' => $search,
                ]) }}"
            >
                Choose another resident
            </a>
        </section>

        <section class="card">
            <h2>Encode document request</h2>

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

                @include('documents.partials.request-fields', [
                    'resident' => $selectedResident,
                    'walkIn' => true,
                ])
            </form>
        </section>
    @endif
@endsection