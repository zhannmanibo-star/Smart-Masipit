@extends('layouts.app')

@section('title', 'Household Change History')
@section('description', $household->household_number)

@section('actions')
    <a
        class="button secondary"
        href="{{ route('households.show', $household) }}"
    >
        Back to household
    </a>
@endsection

@section('content')
    @php
        $actionLabels = [
            'location_updated' => 'Location updated',
            'head_changed' => 'Household head changed',
            'member_transferred_out' => 'Member transferred out',
            'member_transferred_in' => 'Member transferred in',
        ];
    @endphp

    <section class="card">
        <h2>Recorded changes</h2>

        <p class="muted">
            This history includes location edits, head changes,
            and member transfers recorded through the system.
            Earlier actions are not backfilled.
        </p>
    </section>

    @forelse ($changes as $change)
        <section class="card">
            <h2>
                {{ $actionLabels[$change->action] ?? $change->action }}
            </h2>

            <p class="muted">
                {{ $change->occurred_at
                    ->copy()
                    ->timezone('Asia/Manila')
                    ->format('M d, Y · h:i A') }}
                · Philippine time
                · {{ $change->actor?->name ?? 'Unavailable account' }}
            </p>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Field</th>
                            <th scope="col">Before</th>
                            <th scope="col">After</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach (array_unique(array_merge(
                            array_keys($change->before_values ?? []),
                            array_keys($change->after_values ?? [])
                        )) as $field)
                            <tr>
                                <th scope="row">
                                    {{ \Illuminate\Support\Str::headline($field) }}
                                </th>

                                <td style="overflow-wrap: anywhere;">
                                    {{ ($change->before_values[$field] ?? null)
                                        ?: 'Not recorded' }}
                                </td>

                                <td style="overflow-wrap: anywhere;">
                                    {{ ($change->after_values[$field] ?? null)
                                        ?: 'Not recorded' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <strong>Reason</strong>
            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $change->reason }}</p>
        </section>
    @empty
        <section class="card">
            <p>No changes recorded.</p>
        </section>
    @endforelse

    @include('households._pagination', [
        'records' => $changes,
        'paginationLabel' => 'Household change history',
    ])
@endsection