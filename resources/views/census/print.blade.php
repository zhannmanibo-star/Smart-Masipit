<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Resident Census Summary | SMART MASIPIT</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            background: #e5e7eb;
            color: #172b36;
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
        }

        .toolbar {
            max-width: 210mm;
            margin: 0 auto 18px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
        }

        .toolbar a {
            color: #176b4c;
        }

        .toolbar button {
            border: 0;
            border-radius: 6px;
            padding: 12px 20px;
            background: #176b4c;
            color: white;
            font: inherit;
            cursor: pointer;
        }

        .toolbar p {
            width: 100%;
            margin: 0;
        }

        .report {
            max-width: 210mm;
            margin: auto;
            padding: 15mm;
            background: white;
        }

        .report-header {
            display: grid;
            grid-template-columns: 22mm minmax(0, 1fr) 22mm;
            align-items: center;
            gap: 5mm;
            padding-bottom: 5mm;
            border-bottom: 3px double #183b31;
            text-align: center;
        }

        .seal {
            width: 22mm;
            height: 22mm;
            object-fit: contain;
        }

        .report-header p {
            margin: 2px 0;
            font-size: 9pt;
        }

        .report-header h1 {
            margin: 5px 0;
            font-size: 17pt;
        }

        .report-title {
            margin: 7mm 0 2mm;
            text-align: center;
            font-size: 16pt;
        }

        .report-date {
            text-align: center;
            margin: 0 0 5mm;
            font-size: 9pt;
        }

        .scope {
            padding: 4mm;
            border: 1px solid #b8cac2;
            font-size: 9pt;
        }

        .scope p {
            margin: 0 0 2mm;
        }

        .scope p:last-child {
            margin-bottom: 0;
        }

        h3 {
            margin: 6mm 0 2mm;
            font-size: 11pt;
            break-after: avoid;
            page-break-after: avoid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            padding: 2.5mm;
            border: 1px solid #b8cac2;
            text-align: left;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        thead th {
            background: #eef5f2;
        }

        tbody th {
            font-weight: normal;
        }

        .number {
            text-align: right;
            width: 24%;
        }

        .total th,
        .total td {
            font-weight: bold;
        }

        .note {
            margin: 2mm 0;
            color: #475569;
            font-size: 8.5pt;
        }

        .report-footer {
            margin-top: 7mm;
            padding-top: 3mm;
            border-top: 1px solid #b8cac2;
            font-size: 8pt;
            color: #475569;
        }

        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
                color: black;
            }

            .toolbar {
                display: none;
            }

            .report {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .report-header,
            .scope,
            .report-footer {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            thead {
                display: table-header-group;
            }

            tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            thead th {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <nav class="toolbar" aria-label="Report actions">
        <a href="{{ route('census.index') }}">
            Back to census summary
        </a>

        <button type="button" onclick="window.print()">
            Print / Save as PDF
        </button>

        <p>
            Select A4, portrait, and 100% scale in the print dialog.
            Turn off browser headers and footers.
        </p>
    </nav>

    <main class="report">
        <header class="report-header">
            <img class="seal"
                 src="{{ asset('images/seals/masipit.png') }}"
                 alt="Barangay Masipit seal">

            <div>
                <p>REPUBLIC OF THE PHILIPPINES</p>
                <p>Province of Oriental Mindoro</p>
                <p>City of Calapan</p>

                <h1>BARANGAY MASIPIT</h1>

                <p>SMART MASIPIT</p>
            </div>

            <img class="seal"
                 src="{{ asset('images/seals/calapan.png') }}"
                 alt="City of Calapan seal">
        </header>

        <h2 class="report-title">Resident Census Summary</h2>

        <p class="report-date">
            Generated:
            {{ $generatedAt->format('F d, Y · h:i:s A') }}
            — Philippine time
        </p>

        <div class="scope">
            <p>
                <strong>Coverage:</strong>
                Current resident and household records saved in SMART MASIPIT.
                Demographic tables include unarchived residents without
                a recorded death.
            </p>

            <p>
                Age is calculated as of
                {{ $generatedAt->format('F d, Y') }}.
                This registry summary is not an official population census
                or a reconstruction of records on a previous date.
            </p>
        </div>

        <h3>1. Resident record counts</h3>

        <table>
            <thead>
                <tr>
                    <th scope="col">Record group</th>
                    <th scope="col" class="number">Count</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <th scope="row">Active registry residents</th>
                    <td class="number">{{ number_format($activeCount) }}</td>
                </tr>

                <tr>
                    <th scope="row">Recorded deceased — unarchived</th>
                    <td class="number">{{ number_format($deceasedCount) }}</td>
                </tr>

                <tr>
                    <th scope="row">Archived resident records</th>
                    <td class="number">{{ number_format($archivedCount) }}</td>
                </tr>

                <tr class="total">
                    <th scope="row">Total retained resident records</th>
                    <td class="number">
                        {{ number_format($totalRegistryCount) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="note">
            These groups are mutually exclusive. Archived records include
            any archived residents with a recorded death.
        </p>

        @php
            $tables = [
                [
                    'title' => '2. Active residents by sitio',
                    'column' => 'Sitio',
                    'rows' => $sitioCounts,
                ],
                [
                    'title' => '3. Active residents by age group',
                    'column' => 'Age group',
                    'rows' => $ageCounts,
                ],
                [
                    'title' => '4. Active residents by recorded sex',
                    'column' => 'Recorded sex',
                    'rows' => $sexCounts,
                ],
            ];
        @endphp

        @foreach ($tables as $table)
            <h3>{{ $table['title'] }}</h3>

            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ $table['column'] }}</th>
                        <th scope="col" class="number">Residents</th>
                        <th scope="col" class="number">Share</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($table['rows'] as $label => $count)
                        <tr>
                            <th scope="row">{{ $label }}</th>

                            <td class="number">
                                {{ number_format($count) }}
                            </td>

                            <td class="number">
                                {{ $activeCount > 0
                                    ? number_format(
                                        ($count / $activeCount) * 100,
                                        1
                                    ) . '%'
                                    : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                No active resident records available.
                            </td>
                        </tr>
                    @endforelse

                    <tr class="total">
                        <th scope="row">Total</th>
                        <td class="number">
                            {{ number_format($activeCount) }}
                        </td>
                        <td class="number">
                            {{ $activeCount > 0 ? '100.0%' : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        @endforeach

        <p class="note">
            Percentages use the active registry count and are rounded.
            Sitio names follow resident profiles; different spellings
            remain separate. Age groups are system reporting ranges.
        </p>

        <h3>5. Household summary</h3>

        <table>
            <thead>
                <tr>
                    <th scope="col">Measure</th>
                    <th scope="col" class="number">Count</th>
                </tr>
            </thead>

            <tbody>
                @foreach ([
                    'Total household records' => $householdCount,
                    'Households with active members' => $householdsWithActiveMembers,
                    'Households without active members' => $householdsWithoutActiveMembers,
                    'Households needing head review' => $householdsNeedingHeadReview,
                    'Active residents assigned to a household' => $activeResidentsWithHousehold,
                    'Active residents without a household assignment' => $activeResidentsWithoutHousehold,
                ] as $label => $count)
                    <tr>
                        <th scope="row">{{ $label }}</th>
                        <td class="number">{{ number_format($count) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="note">
            Head review is needed when the recorded head is missing,
            archived, or deceased. Household measures overlap and
            should not be added together.
        </p>

        <footer class="report-footer">
            Generated through SMART MASIPIT from saved registry records.
            Contains aggregate counts only.
            Review data completeness before using this report for planning
            or submission.
        </footer>
    </main>
</body>
</html>