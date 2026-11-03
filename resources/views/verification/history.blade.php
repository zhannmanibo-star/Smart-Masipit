@extends('layouts.app')

@section('title', 'Verification History')
@section('description', 'Review completed resident account verification decisions.')

@section('actions')
    <a class="button secondary" href="{{ route('verification.index') }}">
        Pending applications
    </a>
@endsection

@section('content')
    <section class="card" aria-label="Filter verification history">
        <form
            method="GET"
            action="{{ route('verification.history') }}"
            class="toolbar"
            role="search"
        >
            <div class="search-field">
                <label for="search">Search history</label>

                <input
                    id="search"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Applicant, email, reviewer, or resident reference"
                >
            </div>

            <div>
                <label for="decision">Decision</label>

                <select id="decision" name="decision">
                    <option value="">All decisions</option>

                    <option
                        value="approved"
                        @selected($decision === 'approved')
                    >
                        Approved
                    </option>

                    <option
                        value="rejected"
                        @selected($decision === 'rejected')
                    >
                        Rejected
                    </option>
                </select>
            </div>

            <button type="submit">Apply filters</button>

            <a
                class="button secondary"
                href="{{ route('verification.history') }}"
            >
                Clear
            </a>
        </form>
    </section>

    <section class="card" aria-labelledby="history-heading">
        <h2 id="history-heading">
            Completed reviews
            <span class="muted">({{ $reviews->total() }})</span>
        </h2>

        <p class="muted">
            Decisions and review notes are read-only.
            A historical approval does not necessarily indicate
            the account’s current access status.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Applicant</th>
                        <th scope="col">Decision</th>
                        <th scope="col">Reviewed by</th>
                        <th scope="col">Reviewed on (Philippine time)</th>
                        <th scope="col">Review details</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($reviews as $review)
                        <tr>
                            <td>
                                <strong>
                                    {{ $review->last_name }},
                                    {{ $review->first_name }}
                                    {{ $review->middle_name }}
                                </strong>

                                <div class="muted">
                                    {{ $review->applicant_email }}
                                </div>

                                <div class="reference muted">
                                    Application #{{ $review->resident_application_id }}
                                </div>
                            </td>

                            <td>
                                @if ($review->decision === 'approved')
                                    <span class="decision-badge approved">
                                        Approved
                                    </span>
                                @else
                                    <span class="decision-badge rejected">
                                        Rejected
                                    </span>
                                @endif
                            </td>

                            <td>{{ $review->reviewer_name }}</td>

                            <td>
                                {{ \Illuminate\Support\Carbon::parse(
                                    $review->reviewed_at,
                                    config('app.timezone')
                                )->timezone('Asia/Manila')->format('M d, Y · h:i A') }}
                            </td>

                            <td>
                                <details class="review-details">
                                    <summary>View review details</summary>

                                    <div class="review-note">
                                        <strong>
                                            {{ $review->decision === 'approved'
                                                ? 'Verification note'
                                                : 'Reason for rejection' }}
                                        </strong>

                                        <p>{{ $review->note }}</p>

                                        @if ($review->resident_number)
                                            <strong>Linked resident reference</strong>

                                            <p class="reference">
                                                {{ $review->resident_number }}
                                            </p>

                                            @if ($review->resident_deleted_at)
                                                <p class="muted">
                                                    This resident record is
                                                    currently soft-deleted.
                                                </p>
                                            @endif
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">
                                @if ($search !== '' || $decision !== '')
                                    No reviews match your filters.
                                @else
                                    No completed verification reviews yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span class="muted">
                Showing {{ $reviews->firstItem() ?? 0 }}
                to {{ $reviews->lastItem() ?? 0 }}
                of {{ $reviews->total() }} reviews
            </span>

            @if ($reviews->hasPages())
                <nav
                    class="pagination-links"
                    aria-label="Verification history pages"
                >
                    @if ($reviews->previousPageUrl())
                        <a href="{{ $reviews->previousPageUrl() }}">
                            Previous
                        </a>
                    @endif

                    <span>
                        {{ $reviews->currentPage() }}
                        / {{ $reviews->lastPage() }}
                    </span>

                    @if ($reviews->nextPageUrl())
                        <a href="{{ $reviews->nextPageUrl() }}">
                            Next
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection