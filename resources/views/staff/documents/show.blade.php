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
        $item = $documentRequest;
        $type = $item->document_type;
        $supported = isset(\App\Support\CertificateDetails::CODES[$type]);
        $blocked = ! empty($processingBlockReason);
        $closed = $item->status === 'closed_not_released';
        $submitted = is_array($item->request_details) ? $item->request_details : [];
        $approved = is_array($item->approved_request_details) ? $item->approved_request_details : [];
        $verified = ($approved['_verified'] ?? false) === true;
        $definitions = \App\Support\CertificateDetails::fields($type);
        $ownSubmission = (int) $item->requested_by === (int) auth()->id();
        $ownResident = auth()->user()->resident_id !== null
            && (int) auth()->user()->resident_id === (int) $item->resident_id;
       $cannotDecide = $ownResident
    || ($item->source === 'online' && $ownSubmission);
        $cannotRelease = $ownResident || ($item->source === 'online' && $ownSubmission);
        $supplemental = $item->status === 'approved' && ! $item->prepared_at;
        $canPreview = $supported && (
            $item->status === 'released'
            || (! $blocked && in_array($item->status, ['approved', 'ready_for_release'], true))
        );
        $canClose = $blocked && ! $cannotDecide
            && ! $item->released_at && ! $item->closed_at
            && in_array($item->status, [
                'submitted', 'under_review', 'approved', 'ready_for_release'
            ], true);
        $profileFields = [
            'first_name' => 'First name',
            'middle_name' => 'Middle name',
            'last_name' => 'Last name',
            'birth_date' => 'Birth date',
            'sex' => 'Sex',
            'sitio' => 'Sitio',
            'address' => 'Address',
        ];
        $displayValue = function ($value) {
            if (is_bool($value)) {
                return $value ? 'Confirmed' : 'Not confirmed';
            }
            return is_scalar($value) && (string) $value !== ''
                ? (string) $value : 'Not recorded';
        };
    @endphp

    @if ($errors->any())
        <section class="card" role="alert">
            <h2>Please review the following</h2>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($blocked)
        <section class="card" role="alert">
            <h2>Processing blocked</h2>
            <p>{{ $processingBlockReason }}</p>
        </section>
    @endif

    <section class="card">
        <h2>{{ $types[$type] ?? $type }}</h2>
        <p><strong>Status:</strong> {{ $statuses[$item->status] ?? $item->status }}</p>
        <p><strong>Source:</strong> {{ $item->source === 'walk_in' ? 'Walk-in' : 'Online' }}</p>
        <p><strong>Purpose:</strong></p>
        <p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $item->purpose }}</p>

        @if ($item->status === 'rejected')
            <p><strong>Rejection reason:</strong> {{ $item->rejection_reason }}</p>
        @endif

        @if ($closed)
            <p><strong>Closure reason:</strong> {{ $item->closure_reason }}</p>
            <p>This request was closed without release. Its history is retained.</p>
        @endif
    </section>

    <section class="card">
        <h2>Resident information</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Field</th><th>At submission</th>
                        <th>Current registry</th><th>Approved copy</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($profileFields as $field => $label)
                        <tr>
                            <th scope="row">{{ $label }}</th>
                            <td>{{ $item->resident_snapshot[$field] ?? 'Not recorded' }}</td>
                            <td>{{ $currentProfile[$field] ?? 'Unavailable' }}</td>
                            <td>{{ $item->approved_snapshot[$field] ?? 'Not recorded' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Submitted certificate details</h2>
        <p>These details are read-only during approval.</p>

        @if ($definitions)
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Detail</th><th>Submitted</th><th>Verified copy</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($definitions as $field => $definition)
                            <tr>
                                <th scope="row">{{ $definition['label'] }}</th>
                                <td>{{ $displayValue($submitted[$field] ?? null) }}</td>
                                <td>{{ $displayValue($approved[$field] ?? null) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p>Eligibility must be checked against supporting information.</p>
        @endif

        <p>{{ $verified ? 'Certificate verification is recorded.' : 'Certificate verification is pending.' }}</p>

        @if (! empty($approved['_signed_oath_reference']))
            <p><strong>Verified signed oath:</strong> {{ $approved['_signed_oath_reference'] }}</p>
        @endif
    </section>

    @if ($item->status === 'submitted')
        <section class="card">
            <h2>Start review</h2>
            <form method="POST" action="{{ route('staff.documents.status', $item) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="review">
                <button type="submit">Start review</button>
            </form>
        </section>
    @endif

    @if ($item->status === 'under_review' || $supplemental)
        <section class="card">
            <h2>Approval decision</h2>

            @can('approve-document-requests')
                @if ($cannotDecide)
                   <p>
    Another authorized officer must decide a request
    for your own resident record or your own online request.
</p>
                @elseif ($supplemental && ($blocked || ! $supported))
                    <p>Certificate verification is blocked for this request.</p>
                @else
                    <form method="POST" action="{{ route('staff.documents.status', $item) }}">
                        @csrf
                        @method('PATCH')

                        <input type="hidden" name="resident_fingerprint" value="{{ $residentFingerprint }}">

                        @if ($supplemental)
                            <input id="decision_action" type="hidden" name="action" value="review_details">
                            <p>
                                Verify the original submitted details and current
                                registry information before preparation.
                            </p>
                        @else
                            <label for="decision_action">Decision *</label>
                            <select id="decision_action" name="action" required>
                                <option value="">Select a decision</option>
                                @if (! $blocked && $supported && $currentProfile)
                                    <option value="approve" @selected(old('action') === 'approve')>
                                        Approve for document preparation
                                    </option>
                                @endif
                                <option value="reject" @selected(old('action') === 'reject')>
                                    Reject
                                </option>
                            </select>
                        @endif

                        <fieldset id="certificate-verification" style="margin: 20px 0;">
                            <legend>Verify the submitted information</legend>
                            <p>
                                If submitted details are wrong or incomplete, reject
                                with a clear reason so a corrected request can be submitted.
                            </p>

                            <label style="display: flex; gap: 10px;">
                                <input
                                    type="checkbox"
                                    name="eligibility_confirmed"
                                    value="1"
                                    data-approval-required
                                    style="width: auto;"
                                >
                                <span>{{ \App\Support\CertificateDetails::CHECKS[$type] ?? 'I verified eligibility.' }}</span>
                            </label>

                            @if ($type === 'first_time_jobseeker_certification')
                                <label for="signed_oath_reference">
                                    Signed oath reference / barangay record reference *
                                </label>
                                <input
                                    id="signed_oath_reference"
                                    name="signed_oath_reference"
                                    value="{{ old('signed_oath_reference', $approved['_signed_oath_reference'] ?? '') }}"
                                    minlength="3"
                                    maxlength="150"
                                    data-approval-required
                                >
                                <label style="display: flex; gap: 10px;">
                                    <input
                                        type="checkbox"
                                        name="signed_oath_confirmed"
                                        value="1"
                                        data-approval-required
                                        style="width: auto;"
                                    >
                                    <span>
                                        I inspected the signed oath and verified that
                                        it belongs to this applicant. A draft printout
                                        alone is not a signed oath.
                                    </span>
                                </label>
                            @endif
                        </fieldset>

                        <label id="decision-note-label" for="note">Approval note (optional)</label>
                        <textarea
                            id="note"
                            name="note"
                            rows="4"
                            maxlength="2000"
                            style="width: 100%; padding: 12px; font: inherit;"
                        >{{ old('note') }}</textarea>

                        <p class="muted">
                            Approval notes are optional. A rejection reason requires
                            at least 10 characters and is visible to the applicant.
                        </p>

                        <label style="display: flex; gap: 10px; margin: 20px 0;">
                            <input type="checkbox" name="decision_confirmed" value="1" required style="width: auto;">
                            <span>I reviewed the request, resident information, and decision.</span>
                        </label>

                        <button type="submit">
                            {{ $supplemental ? 'Save verification' : 'Save decision' }}
                        </button>
                    </form>
                @endif
            @else
                <p>Awaiting a Secretary, Captain, or authorized approving officer.</p>
            @endcan
        </section>
    @endif

    @if ($item->prepared_at)
        <section class="card">
            <h2>Saved preparation details</h2>
            <p><strong>Control number:</strong> {{ $item->control_number }}</p>
            <p><strong>Issuance date:</strong> {{ $item->issuance_date?->format('F d, Y') }}</p>
            <p><strong>Captain:</strong> {{ $item->signatory_name }}</p>
            @if (! empty($approved['_witness_name']))
                <p>
                    <strong>Witness:</strong>
                    {{ $approved['_witness_name'] }}
                    — {{ $approved['_witness_position'] ?? '' }}
                </p>
            @endif
            @if (! empty($approved['_valid_until']))
                <p><strong>Valid until:</strong> {{ $approved['_valid_until'] }}</p>
            @endif
            <p>
                Prepared:
                {{ $item->prepared_at->copy()->timezone('Asia/Manila')->format('M d, Y · h:i A') }}
            </p>
            @if ($closed)
                <p>Historical details only. This document must not be released.</p>
            @endif
        </section>
    @elseif ($item->status === 'approved' && $supported && ! $blocked)
        <section class="card">
            <h2>Prepare certificate</h2>

            @if (! $verified)
                <p>An authorized officer must verify the certificate details first.</p>
            @else
                <p>Issuance date: {{ now('Asia/Manila')->format('F d, Y') }}</p>
                <p>Captain: {{ $designatedCaptain?->name ?? 'No valid Captain configured' }}</p>

                <form method="POST" action="{{ route('staff.documents.prepare-residency', $item) }}">
                    @csrf

                    @if ($type === 'first_time_jobseeker_certification')
                        <label for="secretary_user_id">Secretary witness *</label>
                        <select id="secretary_user_id" name="secretary_user_id" required>
                            <option value="">Select Secretary</option>
                            @foreach ($secretaries as $secretary)
                                <option value="{{ $secretary->id }}" @selected((string) old('secretary_user_id') === (string) $secretary->id)>
                                    {{ $secretary->name }}
                                </option>
                            @endforeach
                        </select>
                    @elseif ($type === 'oath_of_undertaking')
                        <label for="witness_name">SB Member witness’s full name *</label>
                        <input
                            id="witness_name"
                            name="witness_name"
                            value="{{ old('witness_name') }}"
                            minlength="2"
                            maxlength="150"
                            required
                        >
                    @endif

                    @if (in_array($type, ['oath_of_undertaking', 'first_time_jobseeker_certification'], true))
                        <label style="display: flex; gap: 10px; margin: 18px 0;">
                            <input type="checkbox" name="witness_confirmed" value="1" required style="width: auto;">
                            <span>
                                I verified the witness’s name and designation.
                                Saving a name does not create a signature.
                            </span>
                        </label>
                    @endif

                    <label style="display: flex; gap: 10px; margin: 18px 0;">
                        <input type="checkbox" name="preparation_confirmed" value="1" required style="width: auto;">
                        <span>I checked the approved details, signatory, and issuance date.</span>
                    </label>

                    <button type="submit" @disabled(! $designatedCaptain)>
                        Save preparation details
                    </button>
                </form>
            @endif
        </section>
    @endif

    @if ($canPreview && $item->approved_snapshot)
        <section class="card">
            <h2>Certificate preview</h2>
            <p>The printout remains marked DRAFT and is not valid for release.</p>
            <a
                class="button"
                href="{{ route('staff.documents.residency-preview', $item) }}"
                target="_blank"
                rel="noopener"
            >
                Preview certificate
            </a>
        </section>
    @endif

    @if ($item->ready_at || $item->pickup_instructions)
        <section class="card">
            <h2>Saved pickup details</h2>
            <p style="white-space: pre-wrap;">{{ $item->pickup_instructions }}</p>
            <p>
                Marked ready:
                {{ $item->ready_at?->copy()->timezone('Asia/Manila')->format('M d, Y · h:i A') ?? 'Not recorded' }}
            </p>
            @if ($closed)
                <p>These previous pickup instructions no longer apply.</p>
            @endif
        </section>
    @endif

    @can('process-document-requests')
        @if ($supported && ! $blocked && ! $cannotRelease && $item->status === 'approved' && $item->prepared_at)
            <section class="card">
                <h2>Mark ready for release</h2>
                <p>
                    A draft is not a final document. Actual release requires
                    the completed document and required signatures.
                </p>
                <form method="POST" action="{{ route('staff.documents.release', $item) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="ready">
                    <label for="pickup_instructions">Pickup instructions *</label>
                    <textarea id="pickup_instructions" name="pickup_instructions"
                        rows="4" minlength="10" maxlength="1000" required
                        style="width: 100%; padding: 12px; font: inherit;">{{ old('pickup_instructions') }}</textarea>
                    <label style="display: flex; gap: 10px; margin: 18px 0;">
                        <input type="checkbox" name="confirmation" value="1" required style="width: auto;">
                        <span>I checked the completed document and required signatures.</span>
                    </label>
                    <button type="submit">Mark ready for release</button>
                </form>
            </section>
        @elseif ($supported && ! $blocked && ! $cannotRelease && $item->status === 'ready_for_release')
            <section class="card">
                <h2>Record actual release</h2>
                <form method="POST" action="{{ route('staff.documents.release', $item) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="release">
                    <label for="received_by">Recipient’s full name *</label>
                    <input id="received_by" name="received_by" value="{{ old('received_by') }}" maxlength="150" required>
                    <label for="recipient_type">Recipient type *</label>
                    <select id="recipient_type" name="recipient_type" required>
                        <option value="">Select recipient</option>
                        <option value="resident" @selected(old('recipient_type') === 'resident')>Resident</option>
                        <option value="authorized_representative" @selected(old('recipient_type') === 'authorized_representative')>
                            Authorized representative
                        </option>
                    </select>
                    <label style="display: flex; gap: 10px; margin: 18px 0;">
                        <input type="checkbox" name="confirmation" value="1" required style="width: auto;">
                        <span>I verified identity and authorization and handed over the document.</span>
                    </label>
                    <button type="submit">Confirm actual release</button>
                </form>
            </section>
        @endif
    @endcan

    @if ($item->status === 'released')
        <section class="card">
            <h2>Release details</h2>
            <p>Received by: {{ $item->received_by }}</p>
            <p>
                Recipient type:
                {{ $item->recipient_type === 'authorized_representative' ? 'Authorized representative' : 'Resident' }}
            </p>
            <p>
                Released:
                {{ $item->released_at?->copy()->timezone('Asia/Manila')->format('M d, Y · h:i A') }}
            </p>
        </section>
    @endif

    @can('approve-document-requests')
        @if ($canClose)
            <section class="card">
                <h2>Close blocked request without release</h2>
                <p>Closure is final in this workflow. Records and history are retained.</p>
                <form method="POST" action="{{ route('staff.documents.close', $item) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="expected_status" value="{{ $item->status }}">
                    <label for="closure_reason">Closure reason *</label>
                    <textarea id="closure_reason" name="closure_reason"
                        rows="4" minlength="10" maxlength="2000" required
                        style="width: 100%; padding: 12px; font: inherit;">{{ old('closure_reason') }}</textarea>
                    <label style="display: flex; gap: 10px; margin: 18px 0;">
                        <input type="checkbox" name="closure_confirmed" value="1" required style="width: auto;">
                        <span>I verified the blocking condition and confirmed that the document was not released.</span>
                    </label>
                    <button type="submit" class="danger">Close — Not released</button>
                </form>
            </section>
        @endif
    @endcan

    <section class="card">
        <h2>Request history</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Status change</th><th>Recorded by</th><th>Philippine time</th><th>Note</th></tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>
                                {{ $event->from_status ? ($statuses[$event->from_status] ?? $event->from_status) : 'New request' }}
                                →
                                {{ $statuses[$event->to_status] ?? $event->to_status }}
                            </td>
                            <td>{{ $event->actor_name ?? 'Unavailable account' }}</td>
                            <td>
                                {{ \Illuminate\Support\Carbon::parse($event->occurred_at, config('app.timezone'))
                                    ->timezone('Asia/Manila')->format('M d, Y · h:i A') }}
                            </td>
                            <td style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $event->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No history recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        (() => {
            const action = document.getElementById('decision_action');
            const verification = document.getElementById('certificate-verification');
            const note = document.getElementById('note');
            const label = document.getElementById('decision-note-label');

            if (!action || !verification || !note || !label) return;

            function sync() {
                const approving = ['approve', 'review_details'].includes(action.value);
                const rejecting = action.value === 'reject';

                verification.hidden = !approving;
                verification.disabled = !approving;

                verification.querySelectorAll('[data-approval-required]').forEach(field => {
                    field.required = approving;
                });

                note.required = rejecting;
                note.minLength = rejecting ? 10 : 1;
                label.textContent = rejecting
                    ? 'Reason for rejection *'
                    : 'Approval note (optional)';
            }

            action.addEventListener('change', sync);
            window.addEventListener('pageshow', sync);
            sync();
        })();
    </script>
@endsection