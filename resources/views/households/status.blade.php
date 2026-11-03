@extends('layouts.app')

@section('title', 'Resident Status')
@section('description', 'Record verified status changes and review their history.')

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

        <p class="reference muted">{{ $resident->resident_number }}</p>

        <p>
            Birth date:
            <strong>{{ $resident->birth_date->format('M d, Y') }}</strong>
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
        @endif

        <p class="muted">
            Marking a resident as deceased retains their record and history.
            Clear a recorded death only to correct verified erroneous information.
        </p>

        <form method="POST"
              action="{{ route('residents.status.update', $resident) }}">
            @csrf
            @method('PATCH')

            <input type="hidden"
                   name="status_version"
                   value="{{ old('status_version', $resident->status_version) }}">

            <div class="grid">
                <div>
                    <label for="status">Resident status *</label>

                    <select id="status" name="status" required>
                        <option value="no_recorded_death"
                            @selected(old('status', $resident->isDeceased() ? 'deceased' : 'no_recorded_death') === 'no_recorded_death')>
                            No recorded death
                        </option>

                        <option value="deceased"
                            @selected(old('status', $resident->isDeceased() ? 'deceased' : 'no_recorded_death') === 'deceased')>
                            Deceased
                        </option>
                    </select>
                </div>

                <div>
                    <label for="date_of_death">Date of death</label>

                    <input id="date_of_death"
                           name="date_of_death"
                           type="date"
                           min="{{ $resident->birth_date->format('Y-m-d') }}"
                           max="{{ now('Asia/Manila')->toDateString() }}"
                           value="{{ old('date_of_death', $resident->date_of_death?->format('Y-m-d')) }}">

                    <p class="muted">Required when selecting Deceased.</p>
                </div>
            </div>

            <div style="margin-top: 18px;">
                <label for="reason">Verification or correction note *</label>

                <textarea id="reason"
                          name="reason"
                          rows="4"
                          minlength="10"
                          maxlength="1000"
                          required
                          style="width: 100%; padding: 12px; font: inherit;"
                          placeholder="State the basis for the change and how it was verified.">{{ old('reason') }}</textarea>
            </div>

            <label style="display: flex; gap: 10px; margin-top: 18px;">
                <input type="checkbox"
                       name="confirmed"
                       value="1"
                       required
                       style="width: auto;">

                <span>
                    I verified this information and confirm that the change
                    applies to this resident.
                </span>
            </label>

            <button type="submit">Save status change</button>
        </form>
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
                                {{ \Carbon\Carbon::parse($event->occurred_at, config('app.timezone'))
                                    ->timezone('Asia/Manila')
                                    ->format('M d, Y · h:i A') }}
                            </td>

                            <td>
                                @if ($event->previous_date_of_death)
                                    Deceased —
                                    {{ $event->previous_date_of_death }}
                                @else
                                    No recorded death
                                @endif
                            </td>

                            <td>
                                @if ($event->new_date_of_death)
                                    Deceased —
                                    {{ $event->new_date_of_death }}
                                @else
                                    No recorded death
                                @endif
                            </td>

                            <td style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $event->reason }}</td>

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

        @if ($events->hasPages())
            <nav class="pagination-links" aria-label="Status history pages">
                @if ($events->previousPageUrl())
                    <a href="{{ $events->previousPageUrl() }}">Previous</a>
                @endif

                <span>
                    {{ $events->currentPage() }} / {{ $events->lastPage() }}
                </span>

                @if ($events->nextPageUrl())
                    <a href="{{ $events->nextPageUrl() }}">Next</a>
                @endif
            </nav>
        @endif
    </section>
@endsection