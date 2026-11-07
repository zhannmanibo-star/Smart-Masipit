@php
    $type = $documentRequest->document_type;
    abort_unless(isset(\App\Support\CertificateDetails::CODES[$type]), 404);

    $title = \App\Models\DocumentRequest::TYPES[$type];
    $profile = is_array($documentRequest->approved_snapshot)
        ? $documentRequest->approved_snapshot : [];
    $saved = is_array($documentRequest->approved_request_details)
        ? $documentRequest->approved_request_details : [];
    $verified = ($saved['_verified'] ?? false) === true;
    $details = $verified ? $saved : [];
    $issued = $documentRequest->issuance_date;
    $captain = trim((string) $documentRequest->signatory_name);
    $witness = $details['_witness_name'] ?? '____________________________';

    $parseDate = function ($value) {
        if (! is_string($value)) return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    };

    $birth = $parseDate($profile['birth_date'] ?? null);
    $issuedDate = $issued ? $parseDate($issued->format('Y-m-d')) : null;
    $since = $parseDate($details['residency_since'] ?? null);
    $closure = $parseDate($details['closure_date'] ?? null);
    $validUntil = $parseDate($details['_valid_until'] ?? null);

    $age = $birth && $issuedDate && $birth <= $issuedDate
        ? $birth->diff($issuedDate)->y : null;
    $duration = $since && $issuedDate && $since <= $issuedDate
        ? $since->diff($issuedDate) : null;

    $value = fn ($key) => isset($details[$key]) && is_scalar($details[$key])
        ? (string) $details[$key] : '________________';

    $jobseeker = in_array($type, [
        'oath_of_undertaking', 'first_time_jobseeker_certification'
    ], true);
@endphp

<!DOCTYPE html>
<html lang="{{ $type === 'barangay_occupancy_permit' ? 'fil' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | SMART MASIPIT</title>

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px; background: #e5e7eb;
            color: #111; font-family: "Times New Roman", Times, serif;
        }
        .notice, .actions {
            max-width: 210mm; margin: 0 auto 20px;
            font: 14px/1.6 Arial, sans-serif;
        }
        .notice {
            padding: 16px; background: #fff9e8;
            border: 1px solid #d4b65d; border-radius: 8px;
        }
        .certificate {
            position: relative; width: 210mm; max-width: 100%;
            min-height: 297mm; margin: auto; padding: 15mm 17mm;
            background: white; border: 1px solid #d6c575;
        }
        .certificate::before, .certificate::after {
            content: ""; position: absolute; left: 0; right: 0; height: 4mm;
            background: linear-gradient(105deg, #d8b231, #f3dd78 36%,
                #c89336 57%, #3b4b49 59%, #3b4b49 63%, #e3c151 65%, #f4df8d);
        }
        .certificate::before { top: 0; }
        .certificate::after { bottom: 0; }
        header {
            display: flex; justify-content: center; align-items: center;
            gap: 8mm; margin-bottom: 6mm; break-inside: avoid;
        }
        .seal { width: 24mm; height: 24mm; object-fit: contain; }
        .heading {
            width: 100mm; padding: 3mm; border: 1.5px solid #307da2;
            text-align: center; font-size: 12pt; font-weight: bold; line-height: 1.25;
        }
        .heading p { margin: 0; }
        .office { text-align: center; font-weight: bold; margin: 0 0 7mm; }
        h1 {
            text-align: center; text-transform: uppercase; text-decoration: underline;
            font: bold 18pt/1.3 Arial, sans-serif; margin: 0 0 5mm;
        }
        .subtitle { text-align: center; margin: 0 0 4mm; font-size: 11pt; }
        .draft {
            border: 1px dashed #777; padding: 2mm; text-align: center;
            font: 8pt/1.4 Arial, sans-serif; margin: 0 0 4mm;
        }
        .reference { text-align: right; font-size: 8pt; margin: 1mm 0; overflow-wrap: anywhere; }
        .body { margin-top: 7mm; font-size: 12pt; line-height: 1.65; overflow-wrap: anywhere; }
        .body p { margin: 0 0 6mm; }
        .paragraph { text-indent: 10mm; text-align: justify; }
        .fill { font-weight: bold; text-decoration: underline; }
        .name { text-transform: uppercase; }
        .clearance { max-width: 110mm; margin: 5mm auto 7mm; text-align: center; }
        .line { display: block; border-bottom: 1px solid; font-weight: bold; padding-bottom: 1mm; }
        .caption { display: block; font-size: 10pt; margin-bottom: 4mm; }
        .signature { margin: 18mm auto 0; text-align: center; break-inside: avoid; }
        .signature strong { display: block; text-transform: uppercase; font-size: 12pt; }
        .signature span { display: block; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 8mm; }
        .seal-note { margin-top: 10mm; text-align: right; font-size: 9pt; }
        .oath .body { font-size: 10pt; line-height: 1.3; margin-top: 4mm; }
        .oath .body p { margin-bottom: 3mm; }
        .oath ol { padding-left: 6mm; }
        .oath li { margin-bottom: 2mm; text-align: justify; }
        .oath .signature { margin-top: 9mm; }
        .actions { margin-top: 24px; text-align: center; }
        .actions a { color: #176b4c; margin-right: 20px; }
        button {
            background: #176b4c; color: white; border: 0; border-radius: 7px;
            padding: 12px 20px; cursor: pointer; font: inherit;
        }
        @page { size: A4 portrait; margin: 10mm; }
        @media print {
            body { padding: 0; background: white; }
            .notice, .actions { display: none; }
            .certificate {
                width: 100%; max-width: none; min-height: 275mm;
                margin: 0; padding: 10mm 8mm;
            }
            .certificate, .certificate::before, .certificate::after {
                print-color-adjust: exact; -webkit-print-color-adjust: exact;
            }
        }
        @media screen and (max-width: 600px) {
            body { padding: 10px; }
            .certificate { padding: 12mm 6mm; }
            header { gap: 4mm; }
            .heading { width: auto; flex: 1; font-size: 10pt; }
            .seal { width: 18mm; height: 18mm; }
            h1 { font-size: 16pt; }
        }
    </style>
</head>

<body>
    <aside class="notice">
        <strong>Staff review preview</strong>
        <p>This printout uses saved approved information and remains a draft.</p>
        @if (! $verified)
            <p>Certificate details have not been verified. Missing fields are not verified information.</p>
        @endif
        @if (! $issued)
            <p>Save preparation details to assign the date, control number, and signatories.</p>
        @endif
        <p>Paper: A4. DRAFT — NOT VALID FOR RELEASE.</p>
    </aside>

    <main class="certificate {{ $type === 'oath_of_undertaking' ? 'oath' : '' }}">
        <header>
            <img class="seal" src="{{ asset('images/seals/masipit.png') }}" alt="Barangay Masipit seal">
            <div class="heading">
                <p>Republic of the Philippines</p>
                <p>Province of Oriental Mindoro</p>
                <p>City of Calapan</p>
                <p>Barangay Masipit</p>
            </div>
        </header>

        <p class="office">OFFICE OF THE PUNONG BARANGAY</p>

        <h1>
            {{ $type === 'first_time_jobseeker_certification' ? 'Barangay Certification' : $title }}
        </h1>

        @if ($jobseeker)
            <p class="subtitle">First Time Jobseekers Assistance Act — RA 11261</p>
        @endif

        <p class="draft">
            DRAFT — FOR STAFF REVIEW ONLY. NOT VALID FOR RELEASE.
            @if (! $verified)
                <br>CERTIFICATE DETAILS NOT YET VERIFIED.
            @endif
        </p>

        <p class="reference">
            Control No.: <strong>{{ $documentRequest->control_number ?? 'Not yet assigned' }}</strong>
        </p>
        <p class="reference">Request reference: {{ $documentRequest->reference_number }}</p>

        <div class="body">
            @if (! $jobseeker)
                <p>{{ $type === 'barangay_occupancy_permit' ? 'Sa Kinauukulan:' : 'To Whom It May Concern:' }}</p>
            @endif

            @switch($type)
                @case('barangay_clearance')
                    <p>This is to certify that</p>
                    <div class="clearance">
                        <span class="line name">{{ $fullName }}</span>
                        <span class="caption">(Name)</span>
                        <span class="line">{{ $value('civil_status') }}</span>
                        <span class="caption">(Civil Status)</span>
                        <p><strong class="fill">{{ $age ?? '____' }}</strong> yrs. old</p>
                        <span class="line">{{ $value('occupation') }}</span>
                        <span class="caption">(Occupation)</span>
                    </div>
                    <p>
                        is a bona fide resident of <strong class="fill">{{ $fullAddress }}</strong>
                        and has no pending case in the <em>Lupong Tagapamayapa</em>
                        of Barangay Masipit.
                    </p>
                    @break

                @case('certificate_of_residency')
                    <p class="paragraph">
                        This is to certify that <strong class="fill name">{{ $fullName }}</strong>
                        is a bona fide resident of Barangay Masipit, Calapan City,
                        Oriental Mindoro. He/She has been living in this barangay since
                        <strong class="fill">{{ $since?->format('F j, Y') ?? '________________' }}</strong>
                        up to present.
                    </p>
                    @break

                @case('certificate_of_indigency')
                    <p class="paragraph">
                        This is to certify that <strong class="fill name">{{ $fullName }}</strong>
                        is a bona fide resident of this barangay and belongs to a family
                        whose annual income is below the poverty threshold, and he/she
                        is among the indigent persons in this barangay.
                    </p>
                    @break

                @case('certificate_of_good_moral')
                    <p class="paragraph">
                        This is to certify that <strong class="fill name">{{ $fullName }}</strong>,
                        of legal age, <strong class="fill">{{ $value('civil_status') }}</strong>,
                        Filipino citizen, is a bona fide resident of Masipit,
                        Calapan City, Oriental Mindoro.
                    </p>
                    <p class="paragraph">
                        Furthermore, as per records available in this office, the subject
                        individual has no pending case filed against him/her, is personally
                        known to me to be of good moral character and a law-abiding citizen
                        in the community, and has no derogatory record on file.
                    </p>
                    @break

                @case('certificate_of_closure')
                    <p class="paragraph">
                        This is to certify that
                        <strong class="fill">{{ $value('business_name') }}</strong>,
                        owned by <strong class="fill">{{ $value('owner_name') }}</strong>,
                        located at <strong class="fill">{{ $value('business_address') }}</strong>,
                        Barangay Masipit, Calapan City, Oriental Mindoro,
                        was closed effective
                        <strong class="fill">{{ $closure?->format('F j, Y') ?? '________________' }}</strong>.
                    </p>
                    @break

                @case('barangay_occupancy_permit')
                    <p class="paragraph">
                        Ito ay nagpapatunay na si
                        <strong class="fill name">{{ $fullName }}</strong>
                        ay humihingi ng abiso at pinahihintulutan ng Sangguniang Barangay
                        na makakuha ng <strong>Occupancy Permit</strong> para siya ay
                        makakuha ng <strong>Certificate of Occupancy</strong> sa City.
                        Ang nasabing kahilingan ay napatunayang hindi lumalabag sa mga
                        umiiral na alituntunin at ang tanggapang ito ay walang anumang
                        pagtutol para pagkalooban ng
                        <strong>“LOCATIONAL CLEARANCE ZONING DECISION”</strong>
                        para sa occupancy permit dito sa nasasakupan ng Barangay Masipit,
                        Lungsod ng Calapan.
                    </p>
                    <p>
                        Lokasyon ng ari-arian:
                        <strong class="fill">{{ $value('property_address') }}</strong>,
                        Barangay Masipit, Calapan City, Oriental Mindoro.
                    </p>
                    <p class="paragraph">
                        Ipinagkaloob ang Pagpapatunay na ito ngayong ika-
                        <strong class="fill">{{ $issued?->format('j') ?? '____' }}</strong>
                        ng <strong class="fill">{{ $issued?->format('F Y') ?? '________________' }}</strong>
                        dito sa Tanggapan ng Barangay Masipit, Calapan City, Oriental Mindoro.
                    </p>
                    @break

                @case('first_time_jobseeker_certification')
                    <p class="paragraph">
                        This is to certify that
                        <strong class="fill name">{{ $fullName }}</strong>,
                        a resident of Barangay Masipit, Purok
                        <strong class="fill">{{ $value('purok') }}</strong>
                        for <strong class="fill">{{ $duration?->y ?? '____' }}</strong> years
                        and <strong class="fill">{{ $duration?->m ?? '____' }}</strong> months,
                        is a qualified availee of RA 11261 or the First Time Jobseekers
                        Assistance Act of 2019.
                    </p>
                    <p class="paragraph">
                        I further certify that the holder/bearer was informed of his/her
                        rights, including the duties and responsibilities accorded by
                        RA 11261, through the Oath of Undertaking he/she has signed
                        and executed in the presence of our Barangay Officials.
                    </p>
                    <p class="paragraph">
                        Signed this <strong class="fill">{{ $issued?->format('jS') ?? '____' }}</strong>
                        day of <strong class="fill">{{ $issued?->format('F Y') ?? '________________' }}</strong>
                        in the City of Calapan, Oriental Mindoro.
                    </p>
                    <p class="paragraph">
                        This certification is only valid until
                        <strong class="fill">{{ $validUntil?->format('F j, Y') ?? '________________' }}</strong>,
                        or for one (1) year from the date of issue.
                    </p>
                    @break

                @case('oath_of_undertaking')
                    <p>
                        I, <strong class="fill name">{{ $fullName }}</strong>,
                        <strong class="fill">{{ $age ?? '____' }}</strong> years of age,
                        resident of Barangay Masipit, Purok
                        <strong class="fill">{{ $value('purok') }}</strong>,
                        for <strong class="fill">{{ $duration?->y ?? '____' }}</strong> years
                        and <strong class="fill">{{ $duration?->m ?? '____' }}</strong> months,
                        availing the benefits of Republic Act 11261, otherwise known as
                        the First Time Jobseekers Assistance Act of 2019, do hereby declare,
                        agree, and undertake to abide and be bound by the following:
                    </p>
                    <ol>
                        <li>
                            That this is the first time that I will actively look for a job,
                            and therefore request a Barangay Certification in my favor
                            to avail of the benefits of the law;
                        </li>
                        <li>
                            That I am aware that the benefits and privileges under the law
                            shall be valid only for one (1) year from the date the
                            Barangay Certification is issued;
                        </li>
                        <li>That I can avail of the benefits of the law only ONCE;</li>
                        <li>
                            That I understand that my personal information shall be included
                            in the Roster/List of First Time Jobseekers and will not be
                            used for any unlawful purpose;
                        </li>
                        <li>
                            That I will inform and/or report to barangay personnel through
                            text or other means, or through my family/relatives,
                            once I get employed;
                        </li>
                        <li>
                            That I am not a beneficiary of the JobStart Program under
                            R.A. No. 10869 and other laws that give similar exemptions
                            for documents or transactions exempted under R.A. No. 11261;
                        </li>
                        <li>
                            That if issued the requested Certification, I will not use it
                            in any fraud and will neither falsify nor help or assist
                            in the fabrication of the said certification;
                        </li>
                        <li>
                            That this undertaking is made solely for obtaining a Barangay
                            Certification consistent with the objective of R.A. No. 11261
                            and not for any other purpose;
                        </li>
                        <li>
                            That I consent to the use of my personal information pursuant
                            to the Data Privacy Act and other applicable laws,
                            rules, and regulations.
                        </li>
                    </ol>
                    <p>
                        Signed this <strong class="fill">{{ $issued?->format('jS') ?? '____' }}</strong>
                        day of <strong class="fill">{{ $issued?->format('F Y') ?? '________________' }}</strong>
                        in the City of Calapan, Oriental Mindoro.
                    </p>
                    <div class="signature">
                        <strong>________________________________</strong>
                        <strong>{{ $fullName }}</strong>
                        <span>First Time Jobseeker</span>
                    </div>
                    <p style="margin-top: 5mm;">Witnessed by:</p>
                    @break
            @endswitch

            @if (in_array($type, [
                'barangay_clearance',
                'certificate_of_residency',
                'certificate_of_indigency',
                'certificate_of_good_moral',
                'certificate_of_closure',
            ], true))
                <p class="paragraph">
                    This certification is issued upon request for whatever legal
                    purpose this may serve.
                </p>
                <p class="paragraph">
                    Issued this <strong class="fill">{{ $issued?->format('jS') ?? '____' }}</strong>
                    day of <strong class="fill">{{ $issued?->format('F Y') ?? '________________' }}</strong>
                    at Barangay Masipit, Calapan City, Oriental Mindoro.
                </p>
            @endif

            <div class="{{ $jobseeker ? 'signatures' : '' }}">
                <div class="signature">
                    <strong>{{ $captain !== '' ? $captain : '____________________________' }}</strong>
                    <span>Punong Barangay</span>
                </div>

                @if ($jobseeker)
                    <div class="signature">
                        <strong>{{ $witness }}</strong>
                        <span>
                            {{ $type === 'oath_of_undertaking' ? 'SB Member' : 'Barangay Secretary / Witness' }}
                        </span>
                    </div>
                @endif
            </div>

            @if ($jobseeker)
                <p style="margin-top: 8mm; font-size: 9pt;">
                    Print 2 copies: 1 copy for the availee and 1 copy for the barangay.
                </p>
            @endif

            <div class="seal-note">Note: Not valid without dry seal</div>
        </div>
    </main>

    <nav class="actions">
        <a href="{{ route('staff.documents.show', $documentRequest) }}">Back to request</a>
        <button type="button" onclick="window.print()">Print draft</button>
    </nav>
</body>
</html>