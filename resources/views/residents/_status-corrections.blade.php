<section class="card" id="correction-requests">
    <h2>Correction requests</h2>

    @if (auth()->user()->role === 'captain')
    <p>
        <a href="{{ route('resident-corrections.index') }}">
            Back to correction inbox
        </a>
    </p>
@endif

    @if ($hasPendingCorrection)
        <p role="status">
            A correction request is awaiting Captain review.
            The resident record remains unchanged while pending.
        </p>
    @elseif ($resident->isDeceased() && auth()->user()->role === 'secretary')
        <details>
            <summary style="cursor: pointer;">
                Submit a correction request
            </summary>

            <form
                method="POST"
                action="{{ route('residents.status.corrections.store', $resident) }}"
                style="margin-top: 18px;"
            >
                @csrf

                <input
                    type="hidden"
                    name="status_version"
                    value="{{ old('status_version', $resident->status_version) }}"
                >

                <div>
                    <label for="correction_type">Requested correction *</label>

                    <select id="correction_type" name="correction_type" required>
                        <option value="">Select correction</option>

                        <option value="correct_date"
                            @selected(old('correction_type') === 'correct_date')>
                            Correct the recorded death date
                        </option>

                        <option value="clear_death"
                            @selected(old('correction_type') === 'clear_death')>
                            Remove an erroneous deceased marking
                        </option>
                    </select>
                </div>

                <div style="margin-top: 18px;">
                    <label for="proposed_date_of_death">
                        Corrected date of death
                    </label>

                    <input
                        id="proposed_date_of_death"
                        name="proposed_date_of_death"
                        type="date"
                        min="{{ $resident->birth_date->format('Y-m-d') }}"
                        max="{{ now('Asia/Manila')->toDateString() }}"
                        value="{{ old('proposed_date_of_death') }}"
                    >

                    <p class="muted">
                        Required for a date correction. Ignored when removing
                        an erroneous deceased marking.
                    </p>
                </div>

                <div style="margin-top: 18px;">
                    <label for="supporting_reference">Supporting reference *</label>

                    <input
                        id="supporting_reference"
                        name="supporting_reference"
                        maxlength="255"
                        value="{{ old('supporting_reference') }}"
                        placeholder="Reference identifying the evidence reviewed"
                        required
                    >

                    <p class="muted">
                        Enter a reference to evidence available for the Captain
                        to review. This field does not upload a document.
                    </p>
                </div>

                <div style="margin-top: 18px;">
                    <label for="correction_reason">Reason for correction *</label>

                    <textarea
                        id="correction_reason"
                        name="correction_reason"
                        rows="4"
                        minlength="10"
                        maxlength="1000"
                        style="width: 100%; padding: 12px; font: inherit;"
                        required
                    >{{ old('correction_reason') }}</textarea>
                </div>

                <label style="display: flex; gap: 10px; margin-top: 18px;">
                    <input
                        type="checkbox"
                        name="correction_confirmed"
                        value="1"
                        style="width: auto;"
                        required
                    >

                    <span>
                        I checked the resident identity and supporting
                        information. I am requesting Captain review.
                    </span>
                </label>

                <button type="submit">Submit for Captain review</button>
            </form>
        </details>
    @endif

    @forelse ($corrections as $correction)
        <article style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #d1d5db;">
            <h3>
                Correction #{{ $correction->id }}
                — {{ ucfirst($correction->status) }}
            </h3>

            <p>
                Requested by:
                <strong>{{ $correction->requester_name }}</strong>
                ·
                {{ \Carbon\Carbon::parse($correction->created_at, config('app.timezone'))
                    ->timezone('Asia/Manila')
                    ->format('M d, Y · h:i A') }}
            </p>

            <p>
                Original recorded death date:
                <strong>{{ $correction->original_date_of_death }}</strong>
            </p>

            <p>
                Requested result:
                <strong>
                    @if ($correction->proposed_date_of_death)
                        Deceased — {{ $correction->proposed_date_of_death }}
                    @else
                        Remove erroneous marking — No recorded death
                    @endif
                </strong>
            </p>

            <p>
                Supporting reference:
                {{ $correction->supporting_reference }}
            </p>

            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $correction->reason }}</p>

            @if ($correction->status === 'pending')
                @if (
                    auth()->user()->role === 'captain'
                    && (int) auth()->id() !== (int) $correction->requested_by
                )
                    <form
                        method="POST"
                        action="{{ route('residents.status.corrections.review', [
                            'resident' => $resident,
                            'correction' => $correction->id,
                        ]) }}"
                    >
                        @csrf
                        @method('PATCH')

                        <label for="decision-{{ $correction->id }}">
                            Decision *
                        </label>

                        <select
                            id="decision-{{ $correction->id }}"
                            name="decision"
                            required
                        >
                            <option value="">Select decision</option>
                            <option value="approved">Approve correction</option>
                            <option value="rejected">Reject correction</option>
                        </select>

                        <div style="margin-top: 18px;">
                            <label for="review-note-{{ $correction->id }}">
                                Review note *
                            </label>

                            <textarea
                                id="review-note-{{ $correction->id }}"
                                name="review_note"
                                rows="3"
                                minlength="10"
                                maxlength="1000"
                                style="width: 100%; padding: 12px; font: inherit;"
                                required
                            >{{ old('review_note') }}</textarea>
                        </div>

                        <label style="display: flex; gap: 10px; margin-top: 18px;">
                            <input
                                type="checkbox"
                                name="review_confirmed"
                                value="1"
                                style="width: auto;"
                                required
                            >

                            <span>
                                I reviewed the resident identity, requested
                                correction, and supporting information.
                                Approval will apply the requested change.
                            </span>
                        </label>

                        <button type="submit">Save decision</button>
                    </form>
                @endif
            @else
                <p>
                    Reviewed by:
                    <strong>{{ $correction->reviewer_name }}</strong>
                    ·
                    {{ \Carbon\Carbon::parse($correction->reviewed_at, config('app.timezone'))
                        ->timezone('Asia/Manila')
                        ->format('M d, Y · h:i A') }}
                </p>

                <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $correction->review_note }}</p>
            @endif
        </article>
    @empty
        <p class="muted">No correction requests recorded.</p>
    @endforelse

    @if ($corrections->hasPages())
        <nav class="pagination-links" aria-label="Correction request pages">
            @if ($corrections->previousPageUrl())
                <a href="{{ $corrections->previousPageUrl() }}">Previous</a>
            @endif

            <span>
                {{ $corrections->currentPage() }}
                / {{ $corrections->lastPage() }}
            </span>

            @if ($corrections->nextPageUrl())
                <a href="{{ $corrections->nextPageUrl() }}">Next</a>
            @endif
        </nav>
    @endif
</section>