@extends('layouts.app')

@section('title', 'Resident Correction Inbox')
@section('description', 'Review requests to correct recorded death information.')

@section('actions')
    <a class="button secondary" href="{{ route('dashboard') }}">
        Back to dashboard
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>
            Pending corrections
            <span class="muted">({{ number_format($pendingCount) }})</span>
        </h2>

        <p>
            Open a resident record to review the requested correction
            and supporting reference before making a decision.
        </p>

        <p class="muted">
            This pending count includes all pending requests,
            regardless of the filters below.
        </p>
    </section>

    <section class="card" aria-label="Filter correction requests">
        <form
            method="GET"
            action="{{ route('resident-corrections.index') }}"
            class="toolbar"
            role="search"
        >
            <div class="search-field">
                <label for="search">Find a correction request</label>

                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Resident name, reference, or correction ID"
                >
            </div>

            <div>
                <label for="status">Status</label>

                <select id="status" name="status">
                    <option value="" @selected($status === '')>
                        All statuses
                    </option>

                    @foreach ($statuses as $value => $label)
                        <option
                            value="{{ $value }}"
                            @selected($status === $value)
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit">Apply filters</button>

            <a
                class="button secondary"
                href="{{ route('resident-corrections.index') }}"
            >
                Reset to pending
            </a>
        </form>
    </section>

    <section class="card">
        <h2>
            {{ $status === '' ? 'All correction requests' : $statuses[$status] . ' corrections' }}
            <span class="muted">({{ $corrections->total() }})</span>
        </h2>

        @if ($status === 'pending')
            <p class="muted">Oldest pending requests appear first.</p>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Request / Resident</th>
                        <th scope="col">Requested correction</th>
                        <th scope="col">Submitted</th>
                        <th scope="col">Status / Review</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($corrections as $correction)
                        <tr>
                            <td>
                                <div class="reference">
                                    Correction #{{ $correction->id }}
                                </div>

                                <strong>
                                    {{ $correction->last_name }},
                                    {{ $correction->first_name }}
                                    {{ $correction->middle_name }}
                                </strong>

                                <div class="reference muted">
                                    {{ $correction->resident_number }}
                                </div>

                                @if ($correction->resident_deleted_at)
                                    <div class="muted">
                                        Resident record archived
                                    </div>
                                @endif
                            </td>

                            <td>
                                <div>
                                    Original death date:
                                    {{ \Carbon\Carbon::parse($correction->original_date_of_death)
                                        ->format('M d, Y') }}
                                </div>

                                <p>
                                    @if ($correction->proposed_date_of_death)
                                        <strong>Correct death date to:</strong>

                                        {{ \Carbon\Carbon::parse($correction->proposed_date_of_death)
                                            ->format('M d, Y') }}
                                    @else
                                        <strong>
                                            Remove erroneous deceased marking
                                        </strong>
                                    @endif
                                </p>

                                <details>
                                    <summary style="cursor: pointer;">
                                        Reason and supporting reference
                                    </summary>

                                    <p style="max-width: 350px; white-space: pre-wrap; overflow-wrap: anywhere;">{{ $correction->reason }}</p>

                                    <p style="max-width: 350px; overflow-wrap: anywhere;">
                                        <strong>Supporting reference:</strong>
                                        {{ $correction->supporting_reference }}
                                    </p>
                                </details>
                            </td>

                            <td>
                                <strong>{{ $correction->requester_name }}</strong>

                                <div class="muted">
                                    {{ \Carbon\Carbon::parse(
                                        $correction->created_at,
                                        config('app.timezone')
                                    )->timezone('Asia/Manila')
                                        ->format('M d, Y · h:i A') }}
                                </div>
                            </td>

                            <td>
                                <strong>
                                    {{ $statuses[$correction->status] ?? $correction->status }}
                                </strong>

                                @if ($correction->reviewed_at)
                                    <div>{{ $correction->reviewer_name }}</div>

                                    <div class="muted">
                                        {{ \Carbon\Carbon::parse(
                                            $correction->reviewed_at,
                                            config('app.timezone')
                                        )->timezone('Asia/Manila')
                                            ->format('M d, Y · h:i A') }}
                                    </div>

                                    <details style="margin-top: 8px;">
                                        <summary style="cursor: pointer;">
                                            Review note
                                        </summary>

                                        <p style="max-width: 300px; white-space: pre-wrap; overflow-wrap: anywhere;">{{ $correction->review_note }}</p>
                                    </details>
                                @endif
                            </td>

                            <td>
                                @if ($correction->resident_deleted_at)
                                    <p class="muted">
                                        The resident record must be reviewed
                                        by an administrator before this request
                                        can be opened for processing.
                                    </p>
                                @else
                                    <a
                                        class="button secondary"
                                        href="{{ route('residents.status.edit', [
                                            'resident' => $correction->resident_id,
                                        ]) }}#correction-requests"
                                    >
                                        {{ $correction->status === 'pending'
                                            ? 'Review request'
                                            : 'View resident history' }}
                                    </a>

                                    @if (
                                        $correction->status === 'pending'
                                        && (int) $correction->requested_by === (int) auth()->id()
                                    )
                                        <p class="muted">
                                            You cannot decide your own request.
                                        </p>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">
                                No correction requests match your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span class="muted">
                Showing {{ $corrections->firstItem() ?? 0 }}
                to {{ $corrections->lastItem() ?? 0 }}
                of {{ $corrections->total() }} requests
            </span>

            @if ($corrections->hasPages())
                <nav
                    class="pagination-links"
                    aria-label="Correction inbox pages"
                >
                    @if ($corrections->previousPageUrl())
                        <a href="{{ $corrections->previousPageUrl() }}">
                            Previous
                        </a>
                    @endif

                    <span>
                        {{ $corrections->currentPage() }}
                        / {{ $corrections->lastPage() }}
                    </span>

                    @if ($corrections->nextPageUrl())
                        <a href="{{ $corrections->nextPageUrl() }}">
                            Next
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection