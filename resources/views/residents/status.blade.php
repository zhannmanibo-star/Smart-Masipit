@extends('layouts.app')

@section('title', 'Resident Status')
@section('description', 'Record verified deaths and review resident status history.')

@section('actions')
    <a class="button secondary" href="{{ route('residents.index') }}">
        Back to registry
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>
            {{ $resident->last_name }},
            {{ $resident->first_name }}
            {{ $resident->middle_name }}
        </h2>

        <p class="reference muted">
            {{ $resident->resident_number }}
        </p>

        <p>
            Birth date:
            <strong>
                {{ $resident->birth_date->format('M d, Y') }}
            </strong>
        </p>

        <p>
            Current status:
            <strong>
                {{ $resident->isDeceased() ? 'Deceased' : 'No recorded death' }}
            </strong>
        </p>

        @if ($resident->isDeceased())
            <p>
                Recorded date of death:
                <strong>
                    {{ $resident->date_of_death->format('M d, Y') }}
                </strong>
            </p>

            <div
                role="status"
                style="margin-top: 20px; padding: 18px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc;"
            >
                <strong>This deceased record is locked.</strong>

                <p>
                    The recorded status and date cannot be changed
                    through this form. The resident record and its
                    history are retained.
                </p>

                <p>
                    If the information is incorrect, contact the
                    Barangay Secretary or Captain for review.
                </p>

               <p class="muted">
    The Secretary may submit a correction request below.
    Changes require separate Captain approval and remain
    recorded in the history.
</p>
            </div>
        @else
            <p class="muted">
                Record a death only after verifying the resident's
                identity and supporting information. Once saved,
                the status and date will be locked in this form.
            </p>

            <form
                method="POST"
                action="{{ route('residents.status.update', $resident) }}"
                onsubmit="return confirm('Record this resident as deceased? Confirm that the resident identity and date of death are correct. This form will be locked after saving.');"
            >
                @csrf
                @method('PATCH')

                <input
                    type="hidden"
                    name="status"
                    value="deceased"
                >

                <input
                    type="hidden"
                    name="status_version"
                    value="{{ old('status_version', $resident->status_version) }}"
                >

                <p>
                    Action:
                    <strong>Record verified death</strong>
                </p>

                <div>
                    <label for="date_of_death">
                        Date of death *
                    </label>

                    <input
                        id="date_of_death"
                        name="date_of_death"
                        type="date"
                        min="{{ $resident->birth_date->format('Y-m-d') }}"
                        max="{{ now('Asia/Manila')->toDateString() }}"
                        value="{{ old('date_of_death') }}"
                        aria-describedby="death-date-help"
                        required
                    >

                    <p id="death-date-help" class="muted">
                        Enter the verified date. It cannot be before
                        the resident's birth date or after today.
                    </p>
                </div>

                <div style="margin-top: 18px;">
                    <label for="reason">
                        Verification note *
                    </label>

                    <textarea
                        id="reason"
                        name="reason"
                        rows="4"
                        minlength="10"
                        maxlength="1000"
                        required
                        aria-describedby="verification-help"
                        style="width: 100%; padding: 12px; font: inherit; border: 1px solid #b8cac2; border-radius: 8px;"
                        placeholder="State the supporting reference and how the information was verified."
                    >{{ old('reason') }}</textarea>

                    <p id="verification-help" class="muted">
                        Enter 10–1,000 characters. This note will be
                        retained in the status history.
                    </p>
                </div>

                <label
                    for="confirmed"
                    style="display: flex; align-items: flex-start; gap: 10px; margin-top: 18px;"
                >
                    <input
                        id="confirmed"
                        type="checkbox"
                        name="confirmed"
                        value="1"
                        required
                        style="width: auto; margin-top: 4px;"
                    >

                    <span>
                        I verified the resident's identity, date of death,
                        and supporting information. I understand that
                        the status and date will be locked after saving.
                    </span>
                </label>

                <button type="submit" style="margin-top: 18px;">
                    Record verified death
                </button>
            </form>
        @endif
    </section>

    <section class="card">
        <h2>Status history</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Recorded (Philippine time)</th>
                        <th scope="col">Previous record</th>
                        <th scope="col">New record</th>
                        <th scope="col">Reason</th>
                        <th scope="col">Recorded by</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>
                                {{ \Carbon\Carbon::parse(
                                    $event->occurred_at,
                                    config('app.timezone')
                                )->timezone('Asia/Manila')
                                    ->format('M d, Y · h:i A') }}
                            </td>

                            <td>
                                @if ($event->previous_date_of_death)
                                    <strong>Deceased</strong>

                                    <div class="muted">
                                        {{ \Carbon\Carbon::parse(
                                            $event->previous_date_of_death
                                        )->format('M d, Y') }}
                                    </div>
                                @else
                                    No recorded death
                                @endif
                            </td>

                            <td>
                                @if ($event->new_date_of_death)
                                    <strong>Deceased</strong>

                                    <div class="muted">
                                        {{ \Carbon\Carbon::parse(
                                            $event->new_date_of_death
                                        )->format('M d, Y') }}
                                    </div>
                                @else
                                    No recorded death
                                @endif
                            </td>

                            <td style="max-width: 350px; white-space: pre-wrap; overflow-wrap: anywhere;">{{ $event->reason }}</td>

                            <td>{{ $event->actor_name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">
                                No status changes recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span class="muted">
                Showing {{ $events->firstItem() ?? 0 }}
                to {{ $events->lastItem() ?? 0 }}
                of {{ $events->total() }} entries
            </span>

            @if ($events->hasPages())
                <nav
                    class="pagination-links"
                    aria-label="Status history pages"
                >
                    @if ($events->previousPageUrl())
                        <a href="{{ $events->previousPageUrl() }}">
                            Previous
                        </a>
                    @endif

                    <span>
                        {{ $events->currentPage() }}
                        / {{ $events->lastPage() }}
                    </span>

                    @if ($events->nextPageUrl())
                        <a href="{{ $events->nextPageUrl() }}">
                            Next
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </section>

    @include('residents._status-corrections')


@endsection