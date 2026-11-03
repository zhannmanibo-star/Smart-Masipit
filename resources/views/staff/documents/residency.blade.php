@php
    $documentTitle = \App\Models\DocumentRequest::TYPES[
        $documentRequest->document_type
    ] ?? 'Document';

    $documentType = $documentRequest->document_type;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $documentTitle }} | SMART MASIPIT</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            background: #e5e7eb;
            color: #172b4d;
            font-family: "Times New Roman", serif;
        }

        .certificate {
            width: 100%;
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15mm;
            background: #ffffff;
        }

        .certificate-header {
            display: grid;
            grid-template-columns: 25mm minmax(0, 1fr) 25mm;
            align-items: center;
            gap: 5mm;
            padding-bottom: 5mm;
            border-bottom: 3px double #172b4d;
            text-align: center;
        }

        .certificate-seal {
            display: block;
            width: 25mm;
            height: 25mm;
            object-fit: contain;
        }

        .certificate-heading p {
            margin: 2px 0;
            font-size: 10pt;
        }

        .certificate-heading .republic {
            font-size: 9pt;
            letter-spacing: 1.5px;
        }

        .certificate-heading h2 {
            margin: 8px 0 3px;
            font-size: 11pt;
        }

        .certificate-heading h1 {
            margin: 0;
            font-size: 20pt;
        }

        .certificate-title {
            margin: 10mm 0 6mm;
            text-align: center;
            text-decoration: underline;
            text-transform: uppercase;
            font-size: 18pt;
            letter-spacing: 1.5px;
            overflow-wrap: anywhere;
        }

        .draft-note {
            padding: 10px;
            border: 1px dashed #64748b;
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.5;
        }

        .certificate-body {
            font-size: 12pt;
            line-height: 1.9;
            color: #111827;
            overflow-wrap: anywhere;
        }

        .request-reference {
            margin: 4px 0;
            font-size: 9pt;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .resident-detail {
            text-transform: uppercase;
            text-decoration: underline;
        }

        .purpose {
            padding: 3mm 0;
            font-weight: bold;
            white-space: pre-wrap;
        }

        .review-note {
            padding: 10px;
            border-left: 3px solid #64748b;
            font-size: 10pt;
            line-height: 1.6;
        }

        .signature-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15mm;
            margin-top: 25mm;
            text-align: center;
            break-inside: avoid;
        }

        .signature-line {
            border-top: 1px solid #111827;
            margin-bottom: 2mm;
        }

        .signature-row strong {
            text-transform: uppercase;
        }

        .signature-row small {
            display: block;
            font-size: 9pt;
        }

        .preview-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-top: 30px;
            font-family: Arial, sans-serif;
        }

        .preview-actions a {
            color: #176b4c;
        }

        .preview-actions button {
            padding: 10px 18px;
            border: 0;
            border-radius: 6px;
            background: #176b4c;
            color: white;
            font: inherit;
            cursor: pointer;
        }

        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }

            .certificate {
                max-width: none;
                min-height: auto;
                margin: 0;
                padding: 0;
            }

            .certificate-header {
                break-inside: avoid;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .preview-actions {
                display: none;
            }
        }
    </style>
</head>

<body>
    <main class="certificate">
        <header class="certificate-header">
            <img
                class="certificate-seal"
                src="{{ asset('images/seals/masipit.png') }}"
                alt="Barangay Masipit seal"
            >

            <div class="certificate-heading">
                <p class="republic">REPUBLIC OF THE PHILIPPINES</p>
                <p>Province of Oriental Mindoro</p>
                <p>City of Calapan</p>

                <h2>OFFICE OF THE PUNONG BARANGAY</h2>
                <h1>BARANGAY MASIPIT</h1>
            </div>

            <img
                class="certificate-seal"
                src="{{ asset('images/seals/calapan.png') }}"
                alt="City of Calapan seal"
            >
        </header>

        <h2 class="certificate-title">
            {{ $documentTitle }}
        </h2>

        <p class="draft-note">
            DRAFT — FOR STAFF REVIEW ONLY. NOT VALID FOR RELEASE.
            <br>
            Certificate wording is subject to barangay review.
        </p>

        <p class="request-reference">
            Control No.:
            <strong>
                {{ $documentRequest->control_number ?? 'Not yet assigned' }}
            </strong>
        </p>

        <p class="request-reference">
            Request reference: {{ $documentRequest->reference_number }}
        </p>

        <div class="certificate-body">
            <p>To Whom It May Concern:</p>

            <p>
                This is to certify that
                <strong class="resident-detail">{{ $fullName }}</strong>
                is a bona fide resident of
                <strong class="resident-detail">{{ $fullAddress }}</strong>.
            </p>

            @if ($documentType === 'certificate_of_residency')
                <p>
                    This certification of residency is issued upon
                    the request of the above-named person for
                    the following purpose:
                </p>

            @elseif ($documentType === 'barangay_clearance')
                <p class="review-note">
                    DRAFT REVIEW NOTE: Insert the barangay-approved
                    clearance statement here after completing the
                    required record checks. Residency verification
                    alone does not establish clearance eligibility.
                </p>

                <p>
                    This Barangay Clearance is requested for
                    the following purpose:
                </p>

            @elseif ($documentType === 'certificate_of_indigency')
                <p class="review-note">
                    DRAFT REVIEW NOTE: Insert the barangay-approved
                    indigency certification statement here after
                    verifying the applicant’s eligibility.
                    Residency verification alone does not establish
                    indigency.
                </p>

                <p>
                    This Certificate of Indigency is requested for
                    the following purpose:
                </p>
            @endif

            <p class="purpose">{{ $documentRequest->purpose }}</p>

            <p>
                Issued at Barangay Masipit, Calapan City,
                Oriental Mindoro, Philippines, on
                <strong>
                    {{ $documentRequest->issuance_date?->format('F d, Y')
                        ?? '________________' }}.
                </strong>
            </p>

            <div class="signature-row">
                <div>
                    <div class="signature-line"></div>
                    <strong>{{ $fullName }}</strong>
                    <small>Signature of Applicant</small>
                </div>

                <div>
                    <div class="signature-line"></div>

                    <strong>
                        {{ $documentRequest->signatory_name
                            ?? '________________________' }}
                    </strong>

                    <small>Punong Barangay</small>
                </div>
            </div>
        </div>

        <div class="preview-actions">
            <a href="{{ route('staff.documents.show', $documentRequest) }}">
                Back to request
            </a>

            <button type="button" onclick="window.print()">
                Print draft
            </button>
        </div>
    </main>
</body>
</html>