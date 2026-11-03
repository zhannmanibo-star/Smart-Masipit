@extends('layouts.app')

@section('title', 'My Document Requests')
@section('description', 'View your document requests, status, and pickup instructions.')

@section('actions')
    <a class="button" href="{{ route('documents.create') }}">
        + Request a document
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>
            Your requests
            <span class="muted">({{ $documentRequests->total() }})</span>
        </h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Document</th>
                        <th scope="col">Purpose</th>
                        <th scope="col">Status</th>
                        <th scope="col">Submitted (Philippine time)</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($documentRequests as $documentRequest)
                        <tr>
                            <td class="reference">
                                {{ $documentRequest->reference_number }}
                            </td>

                            <td>
                                {{ $types[$documentRequest->document_type]
                                    ?? $documentRequest->document_type }}
                            </td>

                            <td>
                                <details>
                                    <summary style="cursor: pointer;">View purpose</summary>
                                    <p style="max-width: 320px; white-space: pre-wrap; overflow-wrap: anywhere;">{{ $documentRequest->purpose }}</p>
                                </details>
                            </td>

                            <td>
                                <strong>
                                    {{ $statuses[$documentRequest->status]
                                        ?? $documentRequest->status }}
                                </strong>

                                @if ($documentRequest->status === 'submitted')
                                    <p class="muted">
                                        Your request is awaiting staff review.
                                    </p>
                                @elseif ($documentRequest->status === 'under_review')
                                    <p class="muted">
                                        Barangay staff are reviewing your request.
                                    </p>
                                @elseif ($documentRequest->status === 'approved')
                                    <p class="muted">
                                        Approved for preparation.
                                        Wait until it is marked ready before collecting it.
                                    </p>
                                @elseif ($documentRequest->status === 'rejected')
                                    <details style="margin-top: 8px;">
                                        <summary style="cursor: pointer;">View reason</summary>
                                        <p style="max-width: 320px; white-space: pre-wrap; overflow-wrap: anywhere;">{{ $documentRequest->rejection_reason ?: 'Please contact the barangay office for details.' }}</p>
                                    </details>
                                @elseif ($documentRequest->status === 'ready_for_release')
                                    <div style="margin-top: 8px; max-width: 320px;">
                                        <strong>Ready for pickup at the barangay office</strong>

                                        @if ($documentRequest->pickup_instructions)
                                            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $documentRequest->pickup_instructions }}</p>
                                        @else
                                            <p>Contact the barangay office for pickup instructions.</p>
                                        @endif
                                    </div>
                                @elseif ($documentRequest->status === 'released')
                                    <p class="muted">
                                        Your document has been released.

                                        @if ($documentRequest->released_at)
                                            <br>
                                            {{ $documentRequest->released_at->copy()
                                                ->timezone('Asia/Manila')
                                                ->format('M d, Y · h:i A') }}
                                            (Philippine time)
                                        @endif
                                    </p>
                                @elseif ($documentRequest->status === 'closed_not_released')
                                    <p class="muted">
                                        This request was closed without releasing a document.
                                        Previous pickup instructions no longer apply.
                                    </p>

                                    <details style="margin-top: 8px;">
                                        <summary style="cursor: pointer;">View closure reason</summary>
                                        <p style="max-width: 320px; white-space: pre-wrap; overflow-wrap: anywhere;">{{ $documentRequest->closure_reason ?: 'Please contact the barangay office for details.' }}</p>
                                    </details>

                                    @if ($documentRequest->closed_at)
                                        <p class="muted">
                                            Closed:
                                            {{ $documentRequest->closed_at->copy()
                                                ->timezone('Asia/Manila')
                                                ->format('M d, Y · h:i A') }}
                                            (Philippine time)
                                        </p>
                                    @endif
                                @endif
                            </td>

                            <td>
                                {{ $documentRequest->created_at->copy()
                                    ->timezone('Asia/Manila')
                                    ->format('M d, Y · h:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">
                                You have no document requests yet.
                                Select “Request a document” to begin.
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
                <nav class="pagination-links" aria-label="Request pages">
                    @if ($documentRequests->previousPageUrl())
                        <a href="{{ $documentRequests->previousPageUrl() }}">Previous</a>
                    @endif

                    <span>
                        {{ $documentRequests->currentPage() }}
                        / {{ $documentRequests->lastPage() }}
                    </span>

                    @if ($documentRequests->nextPageUrl())
                        <a href="{{ $documentRequests->nextPageUrl() }}">Next</a>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection