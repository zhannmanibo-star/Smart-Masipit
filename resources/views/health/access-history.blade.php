@extends('layouts.app')

@section('title', 'Health Records Access History')
@section('description', 'Review recorded access and encoding activity within the health module.')

@section('actions')
    <a class="button secondary"
       href="{{ route('health.patients.index') }}">
        Back to patient registry
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>Filter activity</h2>

        <p class="muted">
            Dates and displayed times use Philippine time.
            Opening this page is also recorded in the access history.
        </p>

        @if ($errors->any())
            <div class="errors" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET"
              action="{{ route('health.access-history.index') }}"
              role="search">
            <div class="grid">
                <div>
                    <label for="search">Patient name or resident reference</label>

                    <input id="search"
                           name="search"
                           type="search"
                           maxlength="100"
                           value="{{ $search }}"
                           placeholder="Search patient">
                </div>

                <div>
                    <label for="actor_id">Recorded staff account</label>

                    <select id="actor_id" name="actor_id">
                        <option value="">All staff accounts</option>

                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}"
                                @selected(
                                    (string) $actorId === (string) $member->id
                                )>
                                {{ $member->name }} · ID {{ $member->id }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="action">Action</label>

                    <select id="action" name="action">
                        <option value="">All actions</option>

                        @foreach ($actions as $value => $label)
                            <option value="{{ $value }}"
                                @selected($action === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="from">From date *</label>

                    <input id="from"
                           name="from"
                           type="date"
                           value="{{ $from }}"
                           max="{{ now('Asia/Manila')->toDateString() }}"
                           required>
                </div>

                <div>
                    <label for="to">To date *</label>

                    <input id="to"
                           name="to"
                           type="date"
                           value="{{ $to }}"
                           max="{{ now('Asia/Manila')->toDateString() }}"
                           required>
                </div>
            </div>

            <div class="row-actions" style="margin-top: 18px;">
                <button type="submit">Apply filters</button>

                <a class="button secondary"
                   href="{{ route('health.access-history.index') }}">
                    Reset to last 7 days
                </a>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>
            Recorded activity
            <span class="muted">({{ $logs->total() }})</span>
        </h2>

        <p class="muted">
            Entries identify the signed-in account used for the action.
            These logs cover the health-module actions instrumented
            in SMART MASIPIT.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Recorded time</th>
                        <th scope="col">Staff account</th>
                        <th scope="col">Action</th>
                        <th scope="col">Patient</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>
                                {{ \Carbon\CarbonImmutable::parse(
                                    $log->occurred_at,
                                    config('app.timezone', 'UTC')
                                )->timezone('Asia/Manila')
                                    ->format('M d, Y · h:i:s A') }}

                                <div class="muted">
                                    Philippine time
                                </div>

                                <div class="reference muted">
                                    Log #{{ $log->id }}
                                </div>
                            </td>

                            <td>
                                <strong>
                                    {{ $log->actor_name ?? 'Account unavailable' }}
                                </strong>

                                <div class="muted">
                                    Account ID: {{ $log->actor_id }}
                                </div>
                            </td>

                            <td>
                                {{ $actions[$log->action] ?? $log->action }}
                            </td>

                            <td>
                                @if ($log->health_patient_id)
                                    @if ($log->resident_number)
                                        <strong>
                                            {{ $log->last_name }},
                                            {{ $log->first_name }}
                                            {{ $log->middle_name }}
                                        </strong>

                                        <div class="reference muted">
                                            {{ $log->resident_number }}
                                        </div>
                                    @else
                                        <p>Patient details unavailable.</p>
                                    @endif

                                    <div style="margin-top: 8px;">
                                        <a href="{{ route(
                                            'health.patients.show',
                                            $log->health_patient_id
                                        ) }}">
                                            Open patient profile
                                        </a>
                                    </div>
                                @else
                                    <span class="muted">
                                        Registry or module-level action
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">
                                No recorded activity matches these filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span class="muted">
                Showing {{ $logs->firstItem() ?? 0 }}
                to {{ $logs->lastItem() ?? 0 }}
                of {{ $logs->total() }} entries
            </span>

            @if ($logs->hasPages())
                <nav class="pagination-links"
                     aria-label="Health access history pages">
                    @if ($logs->previousPageUrl())
                        <a href="{{ $logs->previousPageUrl() }}">
                            Previous
                        </a>
                    @endif

                    <span>
                        {{ $logs->currentPage() }}
                        / {{ $logs->lastPage() }}
                    </span>

                    @if ($logs->nextPageUrl())
                        <a href="{{ $logs->nextPageUrl() }}">
                            Next
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection