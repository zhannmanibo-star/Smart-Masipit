@extends('layouts.app')

@section('title', 'Email Monitoring')
@section('description', 'Review failed SMART MASIPIT email jobs and request another delivery attempt.')

@section('actions')
    <a class="button secondary" href="{{ route('admin.dashboard') }}">
        Back to administration
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>Failed email jobs ({{ $jobs->total() }})</h2>

        <p>
            This list contains account-verification and document-pickup
            email jobs that failed after their automatic attempts.
        </p>

        <p class="muted">
            Resolve the delivery problem before retrying.
            A retry sends another email attempt without changing
            the account decision or document status.
        </p>

        <p class="muted">
            This page is not an inbox-delivery report. Jobs still waiting
            in the queue and notifications that could not be queued
            are not included.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Notification</th>
                        <th scope="col">Job reference</th>
                        <th scope="col">Failed (Philippine time)</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td>{{ $job->label }}</td>

                            <td class="reference">
                                {{ $job->uuid }}
                            </td>

                            <td>
                                {{ \Carbon\Carbon::parse(
                                    $job->failed_at,
                                    config('app.timezone')
                                )->timezone('Asia/Manila')
                                    ->format('M d, Y · h:i A') }}
                            </td>

                            <td>
                                <form
                                    method="POST"
                                    action="{{ route('admin.email-monitoring.retry', $job->uuid) }}"
                                    onsubmit="return confirm('Queue another delivery attempt for this email?');"
                                >
                                    @csrf

                                    <label style="display: flex; gap: 8px; align-items: flex-start;">
                                        <input
                                            type="checkbox"
                                            name="confirmed"
                                            value="1"
                                            required
                                            style="width: auto;"
                                        >

                                        <span>
                                            I reviewed the delivery issue
                                            and want to retry this email.
                                        </span>
                                    </label>

                                    <button type="submit">
                                        Retry email
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">
                                No failed SMART MASIPIT email jobs found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($jobs->hasPages())
            <nav class="pagination-links" aria-label="Failed email pages">
                @if ($jobs->previousPageUrl())
                    <a href="{{ $jobs->previousPageUrl() }}">Previous</a>
                @endif

                <span>
                    {{ $jobs->currentPage() }} / {{ $jobs->lastPage() }}
                </span>

                @if ($jobs->nextPageUrl())
                    <a href="{{ $jobs->nextPageUrl() }}">Next</a>
                @endif
            </nav>
        @endif
    </section>
@endsection