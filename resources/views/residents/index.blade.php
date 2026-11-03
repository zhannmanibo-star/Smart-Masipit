@extends('layouts.app')

@section('title', 'Resident Registry')
@section('description', 'Search and maintain resident records for Barangay Masipit.')

@section('actions')
    <div class="row-actions">
        @can('access-admin')
            <a
                class="button secondary"
                href="{{ route('residents.deleted') }}"
            >
                Deleted residents
            </a>
        @endcan

        <a class="button" href="{{ route('residents.create') }}">
            + Add resident
        </a>
    </div>
@endsection

@section('content')
    <section class="card" aria-label="Search residents">
        <form
            method="GET"
            action="{{ route('residents.index') }}"
            class="toolbar"
            role="search"
        >
            <div class="search-field">
                <label for="search">Find a resident</label>

                <input
                    id="search"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Search name, sitio, or reference number"
                >
            </div>

            <div>
    <label for="status">Resident status</label>

    <select id="status" name="status">
        <option value="">All statuses</option>

        <option value="no_recorded_death"
            @selected($status === 'no_recorded_death')>
            No recorded death
        </option>

        <option value="deceased"
            @selected($status === 'deceased')>
            Deceased
        </option>
    </select>
</div>

            <button type="submit">Search</button>

            <a
                class="button secondary"
                href="{{ route('residents.index') }}"
            >
                Clear
            </a>
        </form>
    </section>

    <section class="card" aria-labelledby="records-heading">
        <h2 id="records-heading">
            {{ $search !== '' ? 'Search results' : 'Resident records' }}
            <span class="muted">({{ $residents->total() }})</span>
        </h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Resident</th>
                        <th scope="col">Birth date</th>
                        <th scope="col">Sex</th>
                        <th scope="col">Sitio</th>
                        <th scope="col">Status</th>
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

                                <div class="reference muted">
                                    {{ $resident->resident_number }}
                                </div>
                            </td>

                            <td>
                                {{ $resident->birth_date->format('M d, Y') }}
                            </td>

                            <td>{{ $resident->sex }}</td>

                            <td>{{ $resident->sitio }}</td>

                            <td>
    @if ($resident->isDeceased())
        <strong>Deceased</strong>

        <div class="muted">
            {{ $resident->date_of_death->format('M d, Y') }}
        </div>
    @else
        <span>No recorded death</span>
    @endif
</td>

                            <td>
                                <div class="row-actions">
                                    <a
                                        class="button secondary"
                                        href="{{ route('residents.edit', $resident) }}"
                                        aria-label="Edit {{ $resident->first_name }} {{ $resident->last_name }}"
                                    >
                                        Edit record
                                    </a>

                                    @if (in_array(auth()->user()->role, ['secretary', 'captain'], true))
    <a class="button secondary"
       href="{{ route('residents.status.edit', $resident) }}"
       aria-label="Update status of {{ $resident->first_name }} {{ $resident->last_name }}">
        Update status
    </a>
@endif

                                    @can('access-admin')
                                        <form
                                            method="POST"
                                            action="{{ route('residents.destroy', $resident) }}"
                                            onsubmit="return confirm('Remove this resident from the active registry? The record will be retained for recovery.');"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="danger"
                                                aria-label="Delete {{ $resident->first_name }} {{ $resident->last_name }}"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty">
                                @if ($search !== '')
                                    No matching residents found.
                                    Try another name, sitio, or reference.
                                @else
                                    No resident records yet.
                                    Select “Add resident” to register one.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span class="muted">
                Showing {{ $residents->firstItem() ?? 0 }}
                to {{ $residents->lastItem() ?? 0 }}
                of {{ $residents->total() }} records
            </span>

            @if ($residents->hasPages())
                <nav
                    class="pagination-links"
                    aria-label="Resident list pages"
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
        </div>
    </section>
    
@endsection