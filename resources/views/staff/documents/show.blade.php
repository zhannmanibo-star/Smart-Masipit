@extends('layouts.app')

@section('title', 'Review Document Request')
@section('description', $documentRequest->reference_number)

@section('actions')
    <a class="button secondary" href="{{ route('staff.documents.index') }}">
        Back to requests
    </a>
@endsection

@section('content')
    @php
        $isWalkIn = $documentRequest->source === 'walk_in';
        $isClosed = $documentRequest->status === 'closed_not_released';
        $isBlocked = ! empty($processingBlockReason);

        $sourceLabel = [
            'online' => 'Online',
            'walk_in' => 'Walk-in',
        ][$documentRequest->source] ?? 'Unknown';

        $supportedDocument = array_key_exists(
            $documentRequest->document_type,
            \App\Models\DocumentRequest::TYPES
        );

        $isOwnSubmission = (int) $documentRequest->requested_by === (int) auth()->id();

        $isOwnResidentRecord = auth()->user()->resident_id !== null
            && (int) auth()->user()->resident_id === (int) $documentRequest->resident_id;

        $cannotDecide = $isOwnSubmission || $isOwnResidentRecord;

        $cannotRelease = $isOwnResidentRecord
            || (! $isWalkIn && $isOwnSubmission);

        $canPreview = $supportedDocument
            && (
                $documentRequest->status === 'released'
                || (
                    ! $isBlocked
                    && in_array(
                        $documentRequest->status,
                        ['approved', 'ready_for_release'],
                        true
                    )
                )
            );

        $canClose = $isBlocked
            && ! $cannotDecide
            && ! $documentRequest->released_at
            && ! $documentRequest->closed_at
            && in_array($documentRequest->status, [
                'submitted',
                'under_review',
                'approved',
                'ready_for_release',
            ], true);

        $fields = [
            'first_name' => 'First name',
            'middle_name' => 'Middle name',
            'last_name' => 'Last name',
            'birth_date' => 'Birth date',
            'sex' => 'Sex',
            'sitio' => 'Sitio',
            'address' => 'House / Street / Purok',
        ];
    @endphp

    @if ($isBlocked && ! in_array(
        $documentRequest->status,
        ['released', 'rejected', 'closed_not_released'],
        true
    ))
        <section class="card" role="alert">
            <h2>Document processing blocked</h2>
            <p>{{ $processingBlockReason }}</p>
            <p class="muted">
                Saved details and history are retained.
                The request status has not changed automatically.
            </p>
        </section>
    @endif

    <section class="card">
        <h2>
            {{ $types[$documentRequest->document_type]
                ?? $documentRequest->document_type }}
        </h2>

        <p>
            <strong>Status:</strong>
            {{ $statuses[$documentRequest->status]
                ?? $documentRequest->status }}
        </p>

        <p><strong>Source:</strong> {{ $sourceLabel }}</p>

        @if ($isWalkIn)
            <p class="muted">
                Encoded by barangay staff for a walk-in applicant.
                The encoder is recorded in the request history.
            </p>
        @endif

        <strong>Purpose</strong>
        <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $documentRequest->purpose }}</p>

        @if ($isClosed)
            <div role="status">
                <h3>Closed — Not released</h3>
                <p>This request is closed and cannot continue to preparation or release.</p>

                <strong>Closure reason</strong>
                <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $documentRequest->closure_reason }}</p>

                <p>
                    Closed on:
                    {{ $documentRequest->closed_at?->copy()
                        ->timezone('Asia/Manila')
                        ->format('M d, Y · h:i A') ?? 'Not recorded' }}
                    (Philippine time)
                </p>

                <p>
                    Closed by:
                    {{ $documentRequest->closedBy?->name ?? 'Not recorded' }}
                </p>

                <p class="muted">
                    Preparation details and history are retained.
                    Any existing printout for this request must not be released.
                </p>
            </div>
        @elseif ($documentRequest->status === 'rejected')
            <strong>Reason for rejection</strong>
            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $documentRequest->rejection_reason }}</p>
        @elseif ($documentRequest->status === 'approved')
            <p class="muted">
                Approved for preparation. This does not mean the document
                has been signed or released.
            </p>
        @elseif ($documentRequest->status === 'ready_for_release')
            <p class="muted">
                Recorded as ready for pickup. Release is subject to the
                current eligibility checks.
            </p>
        @elseif ($documentRequest->status === 'released')
            <p>Document release has been recorded.</p>
        @endif
    </section>

    <section class="card">
        <h2>Resident information</h2>

        @if (! $currentProfile)
            <p class="muted">
                The current registry record is unavailable or archived.
                Saved submission and approval details remain below.
            </p>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Field</th>
                        <th scope="col">At submission</th>
                        <th scope="col">Current registry</th>
                        @if ($documentRequest->approved_snapshot)
                            <th scope="col">Approved details</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fields as $key => $label)
                        <tr>
                            <th scope="row">{{ $label }}</th>
                            <td>{{ $documentRequest->resident_snapshot[$key] ?? 'Not recorded' }}</td>
                            <td>{{ $currentProfile[$key] ?? 'Not recorded' }}</td>

                            @if ($documentRequest->approved_snapshot)
                                <td>{{ $documentRequest->approved_snapshot[$key] ?? 'Not recorded' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="muted">
            Approval saves the reviewed resident details.
            Certificate previews use that saved snapshot.
        </p>
    </section>

    @can('process-document-requests')
        @if ($documentRequest->status === 'submitted')
            <section class="card">
                <h2>Start processing</h2>

                <form method="POST"
                      action="{{ route('staff.documents.status', $documentRequest) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="review">
                    <button type="submit">Start review</button>
                </form>
            </section>
        @endif
    @endcan

    @if ($documentRequest->status === 'under_review')
        <section class="card">
            <h2>Approval decision</h2>

            @can('approve-document-requests')
                @if ($cannotDecide)
                    <p class="muted">
                        Another authorized officer must decide a request you
                        submitted or encoded, or a request for your own resident record.
                    </p>
                @else
                    <p class="muted">
                        Rejection reasons are visible to the applicant.
                        Approval notes remain in staff history.
                    </p>

                    <form method="POST"
                          action="{{ route('staff.documents.status', $documentRequest) }}"
                          onsubmit="return confirm('Save this approval decision?');">
                        @csrf
                        @method('PATCH')

                        <input type="hidden"
                               name="resident_fingerprint"
                               value="{{ $residentFingerprint }}">

                        <label for="decision_action">Decision *</label>
                        <select id="decision_action" name="action" required>
                            <option value="">Select a decision</option>

                            @if ($currentProfile && ! $isBlocked && $supportedDocument)
                                <option value="approve" @selected(old('action') === 'approve')>
                                    Approve for document preparation
                                </option>
                            @endif

                            <option value="reject" @selected(old('action') === 'reject')>
                                Reject request
                            </option>
                        </select>

                        <label for="note" style="margin-top: 18px;">
                            Review note / Reason for rejection *
                        </label>
                        <textarea id="note" name="note" rows="4"
                                  minlength="10" maxlength="2000"
                                  style="width: 100%; padding: 12px; font: inherit;"
                                  required>{{ old('note') }}</textarea>

                        <label style="display: flex; gap: 10px; margin: 18px 0;">
                            <input type="checkbox" name="decision_confirmed"
                                   value="1" style="width: auto;" required>
                            <span>I reviewed the request, resident details, and requirements.</span>
                        </label>

                        <button type="submit">Save decision</button>
                    </form>
                @endif
            @else
                <p>Awaiting a decision from the Captain or authorized approving officer.</p>
            @endcan
        </section>
    @endif

    @if ($documentRequest->prepared_at)
        <section class="card">
            <h2>Saved preparation details</h2>
            <dl>
                <dt>Control number</dt>
                <dd>{{ $documentRequest->control_number }}</dd>

                <dt>Issuance date</dt>
                <dd>{{ $documentRequest->issuance_date?->format('F d, Y') ?? 'Not recorded' }}</dd>

                <dt>Saved signatory</dt>
                <dd>{{ $documentRequest->signatory_name }}</dd>

                <dt>Prepared on (Philippine time)</dt>
                <dd>
                    {{ $documentRequest->prepared_at->copy()
                        ->timezone('Asia/Manila')->format('M d, Y · h:i A') }}
                </dd>
            </dl>

            @if ($isClosed)
                <p class="muted">
                    Historical preparation details only.
                    This closed request is not eligible for release.
                </p>
            @endif
        </section>
    @elseif (
        $documentRequest->status === 'approved'
        && $supportedDocument
        && ! $isBlocked
    )
        @can('process-document-requests')
            <section class="card">
                <h2>Prepare document</h2>
                <p>
                    Issuance date:
                    <strong>{{ now('Asia/Manila')->format('F d, Y') }}</strong>.
                    Save only when preparing for this date.
                </p>

                <form method="POST"
                      action="{{ route('staff.documents.prepare-residency', $documentRequest) }}"
                      onsubmit="return confirm('Save the preparation details for this date?');">
                    @csrf

                    <label for="captain_display">Designated Punong Barangay</label>
                    <input id="captain_display" type="text"
                           value="{{ $designatedCaptain?->name ?? 'No valid Captain configured' }}"
                           readonly>

                    <p class="muted">
                        The signatory comes from the designated Captain account.
                    </p>

                    <label style="display: flex; gap: 10px; margin: 18px 0;">
                        <input type="checkbox" name="preparation_confirmed"
                               value="1" style="width: auto;" required>
                        <span>
                            I checked the approved details, purpose,
                            signatory, and issuance date.
                        </span>
                    </label>

                    <button type="submit" @disabled(! $designatedCaptain)>
                        Save preparation details
                    </button>
                </form>
            </section>
        @endcan
    @endif

    @if ($canPreview && $documentRequest->approved_snapshot)
        <section class="card">
            <h2>Certificate preview</h2>
            <p class="muted">
                The preview uses the saved approved details.
                The current printable template is marked DRAFT.
            </p>

            <a class="button"
               href="{{ route('staff.documents.residency-preview', $documentRequest) }}"
               target="_blank" rel="noopener">
                Preview certificate
            </a>
        </section>
    @endif

    @if ($documentRequest->ready_at || $documentRequest->pickup_instructions)
        <section class="card">
            <h2>Saved pickup details</h2>

            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $documentRequest->pickup_instructions }}</p>

            <p class="muted">
                Marked ready:
                {{ $documentRequest->ready_at?->copy()
                    ->timezone('Asia/Manila')->format('M d, Y · h:i A') ?? 'Not recorded' }}
                (Philippine time)
            </p>

            @if ($isClosed)
                <p>These previous pickup instructions no longer apply.</p>
            @endif
        </section>
    @endif

    @can('process-document-requests')
        @if (
            $supportedDocument
            && ! $isBlocked
            && ! $cannotRelease
            && $documentRequest->status === 'approved'
            && $documentRequest->prepared_at
        )
            <section class="card">
                <h2>Mark ready for release</h2>
                <p>
                    Proceed only after the final document is printed,
                    checked, and signed. A draft is not a final document.
                </p>

                <form method="POST"
                      action="{{ route('staff.documents.release', $documentRequest) }}"
                      onsubmit="return confirm('Is the completed and signed document ready for pickup?');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="ready">

                    <label for="pickup_instructions">Pickup instructions *</label>
                    <textarea id="pickup_instructions" name="pickup_instructions"
                              rows="4" minlength="10" maxlength="1000"
                              style="width: 100%; padding: 12px; font: inherit;"
                              required>{{ old('pickup_instructions') }}</textarea>

                    <p class="muted">
                        @if ($isWalkIn)
                            Give these instructions to the walk-in applicant directly.
                        @else
                            These instructions appear on My Document Requests.
                        @endif
                        Saving does not send an email or text message.
                    </p>

                    <label style="display: flex; gap: 10px; margin: 18px 0;">
                        <input type="checkbox" name="confirmation"
                               value="1" style="width: auto;" required>
                        <span>I checked the final document and required signature.</span>
                    </label>

                    <button type="submit">Mark ready for release</button>
                </form>
            </section>
        @elseif (
            $supportedDocument
            && ! $isBlocked
            && ! $cannotRelease
            && $documentRequest->status === 'ready_for_release'
        )
            <section class="card">
                <h2>Record actual release</h2>

                <form method="POST"
                      action="{{ route('staff.documents.release', $documentRequest) }}"
                      onsubmit="return confirm('Has the document actually been handed to this recipient?');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="release">

                    <div class="grid">
                        <div>
                            <label for="received_by">Recipient's full name *</label>
                            <input id="received_by" name="received_by"
                                   value="{{ old('received_by') }}"
                                   maxlength="150" required>
                        </div>

                        <div>
                            <label for="recipient_type">Recipient type *</label>
                            <select id="recipient_type" name="recipient_type" required>
                                <option value="">Select recipient type</option>
                                <option value="resident"
                                    @selected(old('recipient_type') === 'resident')>
                                    Resident
                                </option>
                                <option value="authorized_representative"
                                    @selected(old('recipient_type') === 'authorized_representative')>
                                    Authorized representative
                                </option>
                            </select>
                        </div>
                    </div>

                    <label style="display: flex; gap: 10px; margin: 18px 0;">
                        <input type="checkbox" name="confirmation"
                               value="1" style="width: auto;" required>
                        <span>
                            I verified identity and authorization where applicable,
                            and handed over the document.
                        </span>
                    </label>

                    <button type="submit">Confirm actual release</button>
                </form>
            </section>
        @endif
    @endcan

    @if ($documentRequest->status === 'released')
        <section class="card">
            <h2>Release details</h2>
            <p><strong>Received by:</strong> {{ $documentRequest->received_by }}</p>

            <p>
                <strong>Recipient type:</strong>
                {{ [
                    'resident' => 'Resident',
                    'authorized_representative' => 'Authorized representative',
                ][$documentRequest->recipient_type] ?? 'Not recorded' }}
            </p>

            <p>
                <strong>Released on:</strong>
                {{ $documentRequest->released_at?->copy()
                    ->timezone('Asia/Manila')->format('M d, Y · h:i A') ?? 'Not recorded' }}
                (Philippine time)
            </p>
        </section>
    @endif

    @can('approve-document-requests')
        @if ($canClose)
            <section class="card">
                <h2>Close blocked request without release</h2>

                <p>
                    Use this action after reviewing why the request cannot continue.
                    Closure is final in this workflow and does not delete its records.
                </p>

                <p class="muted">
                    The reason will be visible to the resident.
                    If a status correction is still being reviewed,
                    consider waiting for its outcome before closing.
                </p>

                <form method="POST"
                      action="{{ route('staff.documents.close', $documentRequest) }}"
                      onsubmit="return confirm('Close this request without release? This action cannot be undone through this workflow.');">
                    @csrf
                    @method('PATCH')

                    <input type="hidden" name="expected_status"
                           value="{{ $documentRequest->status }}">

                    <label for="closure_reason">Closure reason *</label>
                    <textarea id="closure_reason" name="closure_reason"
                              rows="4" minlength="10" maxlength="2000"
                              style="width: 100%; padding: 12px; font: inherit;"
                              required>{{ old('closure_reason') }}</textarea>

                    <label style="display: flex; gap: 10px; margin: 18px 0;">
                        <input type="checkbox" name="closure_confirmed"
                               value="1" style="width: auto;" required>
                        <span>
                            I reviewed the blocking condition, confirmed that the
                            document was not released, and verified the closure reason.
                        </span>
                    </label>

                    <button type="submit" class="danger">
                        Close — Not released
                    </button>
                </form>
            </section>
        @elseif (
            $isBlocked
            && $cannotDecide
            && in_array($documentRequest->status, [
                'submitted', 'under_review', 'approved', 'ready_for_release',
            ], true)
        )
            <section class="card">
                <p>
                    Another authorized approving officer must review
                    and close this request.
                </p>
            </section>
        @endif
    @endcan

    <section class="card">
        <h2>Request history</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Status change</th>
                        <th scope="col">Recorded by</th>
                        <th scope="col">Philippine time</th>
                        <th scope="col">Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>
                                {{ $event->from_status
                                    ? ($statuses[$event->from_status] ?? $event->from_status)
                                    : 'New request' }}
                                →
                                {{ $statuses[$event->to_status] ?? $event->to_status }}
                            </td>
                            <td>{{ $event->actor_name }}</td>
                            <td>
                                {{ \Illuminate\Support\Carbon::parse(
                                    $event->occurred_at,
                                    config('app.timezone')
                                )->timezone('Asia/Manila')->format('M d, Y · h:i A') }}
                            </td>
                            <td style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $event->note }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">No history entries recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection