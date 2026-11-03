@extends('layouts.app')

@section('title', 'Account Verification')
@section('description', 'Review resident applications awaiting barangay verification.')
@section('actions')
    <a class="button secondary" href="{{ route('verification.history') }}">
        Verification history
    </a>
@endsection
@section('content')
    <section class="card" aria-label="Search applications">
        <form
            method="GET"
            action="{{ route('verification.index') }}"
            class="toolbar"
            role="search"
        >
            <div class="search-field">
                <label for="search">Find an applicant</label>

                <input
                    id="search"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Search name, email, or sitio"
                >
            </div>

            <button type="submit">Search</button>

            <a
                class="button secondary"
                href="{{ route('verification.index') }}"
            >
                Clear
            </a>
        </form>
    </section>

    <section class="card" aria-labelledby="applications-heading">
        <h2 id="applications-heading">
            Pending applications
            <span class="muted">({{ $applications->total() }})</span>
        </h2>

        <p class="muted">
            These details were submitted by applicants and have not
            yet been verified. Applications are listed oldest first.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Applicant</th>
                        <th scope="col">Birth date</th>
                        <th scope="col">Sitio</th>
                        <th scope="col">Submitted (Philippine time)</th>
                        <th scope="col">Details</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            <td>
                                <strong>
                                    {{ $application->last_name }},
                                    {{ $application->first_name }}
                                    {{ $application->middle_name }}
                                </strong>

                                <div class="muted">
                                    {{ $application->user->email }}
                                </div>

                                <div class="reference muted">
                                    Application #{{ $application->id }}
                                </div>
                            </td>

                            <td>
                                {{ $application->birth_date->format('M d, Y') }}
                            </td>

                            <td>{{ $application->sitio }}</td>

                            <td>
                                {{ $application->created_at
                                    ->copy()
                                    ->timezone('Asia/Manila')
                                    ->format('M d, Y · h:i A') }}
                            </td>

                          <td>
    <a
        class="button secondary"
        href="{{ route('verification.show', $application) }}"
    >
        Review application
    </a>
</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">
                                @if ($search !== '')
                                    No pending applications match your search.
                                @else
                                    No resident applications are awaiting verification.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span class="muted">
                Showing {{ $applications->firstItem() ?? 0 }}
                to {{ $applications->lastItem() ?? 0 }}
                of {{ $applications->total() }} applications
            </span>

            @if ($applications->hasPages())
                <nav
                    class="pagination-links"
                    aria-label="Application list pages"
                >
                    @if ($applications->previousPageUrl())
                        <a href="{{ $applications->previousPageUrl() }}">
                            Previous
                        </a>
                    @endif

                    <span>
                        {{ $applications->currentPage() }}
                        / {{ $applications->lastPage() }}
                    </span>

                    @if ($applications->nextPageUrl())
                        <a href="{{ $applications->nextPageUrl() }}">
                            Next
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection