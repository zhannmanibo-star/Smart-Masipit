@extends('layouts.app')

@section('title', 'Deleted Residents')
@section('description', 'Review and recover resident records removed from the active registry.')

@section('actions')
    <a class="button secondary" href="{{ route('residents.index') }}">
        Back to active registry
    </a>
@endsection

@section('content')
    @if (session('restore_match_id'))
        <section class="card">
            <h2>Matching active record</h2>

            <p>
                Review this record to determine whether it represents
                the same person.
            </p>

            <p class="reference">
                {{ session('restore_match_reference') }}
            </p>

            <a
                class="button secondary"
                href="{{ route('residents.edit', session('restore_match_id')) }}"
            >
                Review active record
            </a>
        </section>
    @endif

    <section class="card" aria-label="Search deleted residents">
        <form
            method="GET"
            action="{{ route('residents.deleted') }}"
            class="toolbar"
            role="search"
        >
            <div class="search-field">
                <label for="search">Find a deleted resident</label>

                <input
                    id="search"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Search name or resident reference"
                >
            </div>

            <button type="submit">Search</button>

            <a
                class="button secondary"
                href="{{ route('residents.deleted') }}"
            >
                Clear
            </a>
        </form>
    </section>

    <section class="card" aria-labelledby="deleted-heading">
        <h2 id="deleted-heading">
            Deleted records
            <span class="muted">({{ $residents->total() }})</span>
        </h2>

        <p class="muted">
            Restoring a record returns it to the active registry
            with its original reference number.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Resident</th>
                        <th scope="col">Birth date</th>
                        <th scope="col">Sitio</th>
                        <th scope="col">Deleted on (Philippine time)</th>
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

                            <td>{{ $resident->sitio }}</td>

                            <td>
                                {{ $resident->deleted_at
                                    ->copy()
                                    ->timezone('Asia/Manila')
                                    ->format('M d, Y · h:i A') }}
                            </td>

                            <td>
                                <form
                                    method="POST"
                                    action="{{ route('residents.restore', $resident->id) }}"
                                    onsubmit="return confirm('Restore this resident to the active registry?');"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <button
                                        type="submit"
                                        aria-label="Restore {{ $resident->first_name }} {{ $resident->last_name }}"
                                    >
                                        Restore
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">
                                @if ($search !== '')
                                    No deleted residents match your search.
                                @else
                                    No deleted resident records.
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
                    aria-label="Deleted resident list pages"
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