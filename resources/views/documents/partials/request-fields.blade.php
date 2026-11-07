@php
    $definitions = \App\Support\CertificateDetails::FIELDS;
    $typeFields = \App\Support\CertificateDetails::TYPE_FIELDS;
@endphp

<input
    type="hidden"
    name="submission_token"
    value="{{ old('submission_token', $submissionToken) }}"
>

<label for="document_type">Document type *</label>
<select id="document_type" name="document_type" required>
    <option value="">Select a document</option>

    @foreach ($types as $value => $label)
        <option value="{{ $value }}" @selected(old('document_type') === $value)>
            {{ $label }}
        </option>
    @endforeach
</select>

<p class="muted">
    Additional information is subject to barangay verification.
    Submitted certificate details cannot be edited during approval.
</p>

@foreach ($definitions as $field => $definition)
    @php
        $applicableTypes = array_keys(array_filter(
            $typeFields,
            fn ($fields) => in_array($field, $fields, true)
        ));
    @endphp

    <div
        data-certificate-field
        data-types="{{ implode(',', $applicableTypes) }}"
        style="margin: 18px 0;"
    >
        @if ($definition['type'] === 'checkbox')
            <label style="display: flex; gap: 10px;">
                <input
                    type="checkbox"
                    name="details[{{ $field }}]"
                    value="1"
                    style="width: auto;"
                    @checked(old('details.' . $field))
                >
                <span>{{ $definition['label'] }} *</span>
            </label>
        @else
            <label for="detail_{{ $field }}">{{ $definition['label'] }} *</label>

            @if ($definition['type'] === 'select')
                <select id="detail_{{ $field }}" name="details[{{ $field }}]">
                    <option value="">Select an option</option>

                    @foreach ($definition['options'] as $option)
                        <option
                            value="{{ $option }}"
                            @selected(old('details.' . $field) === $option)
                        >
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            @else
                <input
                    id="detail_{{ $field }}"
                    name="details[{{ $field }}]"
                    type="{{ $definition['type'] }}"
                    value="{{ old('details.' . $field) }}"
                    @if (isset($definition['max']))
                        maxlength="{{ $definition['max'] }}"
                    @endif
                    @if ($definition['type'] === 'date')
                        max="{{ now('Asia/Manila')->toDateString() }}"
                    @endif
                    @if ($field === 'residency_since')
                        min="{{ $resident->birth_date->format('Y-m-d') }}"
                    @endif
                >
            @endif
        @endif
    </div>
@endforeach

<p id="jobseeker-note" class="muted" hidden>
    The oath requires the applicant’s actual signature and witnesses.
    First-Time Jobseeker Certification requires verification of the signed
    oath and eligibility records. Submitting this form does not execute an oath.
</p>

<p id="good-moral-note" class="muted" hidden>
    The supplied Good Moral template is for an applicant of legal age
    and states Filipino citizenship. Barangay verification is required.
</p>

<label for="purpose">Purpose of request *</label>
<textarea
    id="purpose"
    name="purpose"
    rows="4"
    minlength="5"
    maxlength="1000"
    required
    style="width: 100%; padding: 12px; font: inherit;"
>{{ old('purpose') }}</textarea>

<label style="display: flex; gap: 10px; margin: 20px 0;">
    <input
        type="checkbox"
        name="{{ $walkIn ? 'identity_confirmed' : 'details_confirmed' }}"
        value="1"
        required
        style="width: auto;"
    >

    <span>
        @if ($walkIn)
            I verified the applicant’s identity and confirmed the selected
            resident, document, purpose, and submitted details with the applicant.
        @else
            I confirm that the information is correct and that I am
            requesting this document for myself.
        @endif
    </span>
</label>

<p class="muted">
    Submission is subject to review and approval.
    It does not mean that a document has been issued.
</p>

<button type="submit">
    {{ $walkIn ? 'Record walk-in request' : 'Submit request' }}
</button>

<noscript>
    <p>Please enable JavaScript to show the fields for your selected document.</p>
</noscript>

<script>
    (() => {
        const type = document.getElementById('document_type');
        const groups = document.querySelectorAll('[data-certificate-field]');

        function syncFields() {
            groups.forEach(group => {
                const active = group.dataset.types.split(',').includes(type.value);
                group.hidden = !active;

                group.querySelectorAll('input, select, textarea').forEach(field => {
                    field.disabled = !active;
                    field.required = active;
                });
            });

            document.getElementById('jobseeker-note').hidden = ![
                'oath_of_undertaking',
                'first_time_jobseeker_certification'
            ].includes(type.value);

            document.getElementById('good-moral-note').hidden =
                type.value !== 'certificate_of_good_moral';
        }

        type.addEventListener('change', syncFields);
        window.addEventListener('pageshow', syncFields);
        syncFields();
    })();
</script>