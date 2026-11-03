@extends('layouts.app')

@section('title', 'Document Requests')
@section('description', 'Review and process resident document requests. Oldest requests appear first.')

@section('actions')
    @can('process-document-requests')
        <a
            class="button"
            href="{{ route('staff.documents.walk-in.create') }}"
        >
            + Walk-in request
        </a>
    @endcan
@endsection

@section('content')
    <section class="card">
        <form
            method="GET"
            action="{{ route('staff.documents.index') }}"
            class="toolbar"
            role="search"
        >
            <div class="search-field">
                <label for="search">Request reference</label>

                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Search request reference"
                >
            </div>

            <div>
                <label for="status">Status</label>

                <select id="status" name="status">
                    <option value="">All statuses</option>

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

            <div>
    <label for="released_on">Release date (Philippine time)</label>

    <input
        id="released_on"
        type="date"
        name="released_on"
        value="{{ $releasedOn ?? '' }}"
    >
</div>

            <button type="submit">Apply</button>

            <a
                class="button secondary"
                href="{{ route('staff.documents.index') }}"
            >
                Clear
            </a>
        </form>
    </section>

    <section class="card">
        <h2>
            Requests
            <span class="muted">({{ $documentRequests->total() }})</span>
        </h2>

        @if (! empty($releasedOn))
    <p class="muted">
        Showing requests released on
        {{ \Carbon\CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $releasedOn,
            'Asia/Manila'
        )->format('F d, Y') }}
        in Philippine time.
    </p>
@endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Reference / Resident</th>
                        <th scope="col">Document</th>
                        <th scope="col">Source</th>
                        <th scope="col">Status</th>
                        <th scope="col">Submitted (Philippine time)</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($documentRequests as $item)
                        <tr>
                            <td>
                                <div class="reference">
                                    {{ $item->reference_number }}
                                </div>

                                <strong>
                                    {{ $item->resident_snapshot['first_name'] ?? '' }}
                                    {{ $item->resident_snapshot['middle_name'] ?? '' }}
                                    {{ $item->resident_snapshot['last_name'] ?? '' }}
                                </strong>

                                @if ($item->control_number)
                                    <div class="muted reference">
                                        Control No.: {{ $item->control_number }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                {{ $types[$item->document_type]
                                    ?? $item->document_type }}
                            </td>

                            <td>
                                {{ [
                                    'online' => 'Online',
                                    'walk_in' => 'Walk-in',
                                ][$item->source] ?? 'Unknown' }}
                            </td>

                            <td>
                                <strong>
                                    {{ $statuses[$item->status]
                                        ?? $item->status }}
                                </strong>
                            </td>

                            <td>
                                {{ $item->created_at
                                    ->copy()
                                    ->timezone('Asia/Manila')
                                    ->format('M d, Y · h:i A') }}
                            </td>

                            <td>
                                <a
                                    class="button secondary"
                                    href="{{ route('staff.documents.show', $item) }}"
                                    aria-label="Open request {{ $item->reference_number }}"
                                >
                                    Open request
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty">
                                @if ($search !== '' || $status !== '')
                                    No requests match your filters.
                                    Try another reference or clear the filters.
                                @else
                                    No document requests yet.
                                    Online submissions and encoded walk-in
                                    requests will appear here.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span class="muted">
                Showing {{ $documentRequests->firstItem() ?? 0 }}
                to {{ $documentRequests->lastItem() ?? 0 }}
                of {{ $documentRequests->total() }} requests
            </span>

            @if ($documentRequests->hasPages())
                <nav
                    class="pagination-links"
                    aria-label="Request pages"
                >
                    @if ($documentRequests->previousPageUrl())
                        <a href="{{ $documentRequests->previousPageUrl() }}">
                            Previous
                        </a>
                    @endif

                    <span>
                        {{ $documentRequests->currentPage() }}
                        / {{ $documentRequests->lastPage() }}
                    </span>

                    @if ($documentRequests->nextPageUrl())
                        <a href="{{ $documentRequests->nextPageUrl() }}">
                            Next
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection