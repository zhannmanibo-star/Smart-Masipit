@php
    $s = $record->patient_snapshot;
    $d = $record->details;

    $value = fn (string $key) => $d[$key] ?? '';

    $dateValue = function (string $key) use ($d) {
        return ! empty($d[$key])
            ? \Carbon\Carbon::parse($d[$key])->format('m/d/Y')
            : '';
    };

    $mark = function (string $key) use ($d) {
        return match ($d[$key] ?? null) {
            'yes' => '✓',
            'no' => '—',
            default => '?',
        };
    };

    $yesNo = function (string $key) use ($d) {
        return match ($d[$key] ?? null) {
            'yes' => '(✓) YES   ( ) NO',
            'no' => '( ) YES   (✓) NO',
            default => '( ) YES   ( ) NO',
        };
    };

    $blankRows = max(0, 7 - $visits->count());
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Maternal Health Record — Draft</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            background: #e5e7eb;
            color: #000;
            font-family: Arial, sans-serif;
        }

        .actions {
            max-width: 210mm;
            margin: 0 auto 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .actions a { color: #176b4c; }

        .actions button {
            padding: 12px 18px;
            border: 0;
            border-radius: 6px;
            background: #176b4c;
            color: white;
            cursor: pointer;
            font: inherit;
        }

        .paper {
            width: 210mm;
            min-height: 297mm;
            margin: auto;
            padding: 10mm;
            background: white;
            font-size: 8.5pt;
            line-height: 1.25;
        }

        .header {
            display: grid;
            grid-template-columns: 22mm 1fr 22mm;
            align-items: center;
            gap: 8mm;
            text-align: center;
        }

        .seal {
            width: 21mm;
            height: 21mm;
            object-fit: contain;
        }

        .missing-logo {
            width: 21mm;
            height: 21mm;
            display: grid;
            place-items: center;
            border: 1px dashed #777;
            font-size: 7pt;
            color: #555;
        }

        .government {
            font-family: "Times New Roman", serif;
            font-size: 10pt;
            line-height: 1.3;
        }

        .department {
            font-family: "Times New Roman", serif;
            font-size: 10pt;
            font-weight: bold;
            text-align: center;
            margin: 5mm 0 2mm;
        }

        h1 {
            margin: 0;
            padding-top: 2mm;
            border-top: 1.5px solid #000;
            text-align: center;
            font-size: 18pt;
        }

        .draft {
            margin: 2mm 0 3mm;
            text-align: center;
            font-size: 7pt;
            letter-spacing: .3px;
        }

        .line {
            display: flex;
            align-items: baseline;
            gap: 1mm;
            margin: 1.5mm 0;
        }

        .fill {
            flex: 1;
            min-width: 0;
            min-height: 3.5mm;
            border-bottom: 1px solid #000;
            padding: 0 1mm;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .two {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 6mm;
        }

        .number {
            width: 46%;
            margin-left: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #777;
            padding: 1.5mm;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        .identity { margin-top: 3mm; }
        .identity th { font-size: 8.5pt; text-align: center; }
        .identity td {
            text-align: center;
            min-height: 8mm;
            font-family: "Times New Roman", serif;
            font-size: 10pt;
        }

        .section {
            margin-top: 2mm;
            padding-top: 2mm;
            border-top: 1.5px solid #000;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 1mm;
        }

        .compact { margin: 1mm 0; }
        .obstetric { line-height: 1.7; }

        .inline-value {
            display: inline-block;
            min-width: 4mm;
            padding: 0 .5mm;
            text-align: center;
        }

        .symptoms {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1mm 5mm;
        }

        .check-row {
            display: flex;
            justify-content: space-between;
            gap: 2mm;
            margin: 1mm 0;
        }

        .box {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 3.5mm;
            height: 3.5mm;
            border: 1px solid #555;
            flex: 0 0 3.5mm;
            font-size: 8pt;
        }

        .legend {
            margin: 1mm 0;
            font-size: 6.5pt;
        }

        .visits-title {
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            margin: 4mm 0 1.5mm;
        }

        .visits th {
            text-align: center;
            font-size: 7.5pt;
        }

        .visits td {
            font-size: 7.5pt;
            white-space: pre-wrap;
        }

        .visits .blank td { height: 12mm; }

        .footer {
            margin-top: 2mm;
            font-size: 6.5pt;
            overflow-wrap: anywhere;
        }

        .appendix {
            break-before: page;
            margin-top: 8mm;
        }

        .note {
            border-bottom: 1px solid #999;
            margin-bottom: 5mm;
            padding-bottom: 3mm;
        }

        .note-text {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }

            .actions { display: none; }

            .paper {
                width: auto;
                min-height: 0;
                padding: 0;
                margin: 0;
            }

            .header,
            .identity,
            .section {
                break-inside: avoid;
            }

            thead { display: table-header-group; }
            tr { break-inside: avoid; }
            .appendix { margin-top: 0; }
        }

        @media screen and (max-width: 850px) {
            body { overflow-x: auto; }
        }
    </style>
</head>

<body>
    <nav class="actions">
        <a href="{{ route('health.maternal.show', [$patient, $record]) }}">
            Back to record
        </a>

        <button type="button" onclick="window.print()">
            Print draft
        </button>

        <span>A4 portrait · Scale 100% · Browser headers/footers off</span>
    </nav>

    <main class="paper">
        <header class="header">
            <img class="seal"
                 src="{{ asset('images/seals/calapan.png') }}"
                 alt="City of Calapan seal">

            <div class="government">
                Republic of the Philippines<br>
                Province of Oriental Mindoro<br>
                CITY OF CALAPAN
            </div>

            @if (is_file(public_path('images/seals/city-health.png')))
                <img class="seal"
                     src="{{ asset('images/seals/city-health.png') }}"
                     alt="City Health and Sanitation Department seal">
            @else
                <div class="missing-logo">
                    CITY HEALTH<br>LOGO PENDING
                </div>
            @endif
        </header>

        <div class="department">
            CITY HEALTH AND SANITATION DEPARTMENT
        </div>

        <h1>MATERNAL HEALTH RECORD</h1>

        <div class="draft">
            DRAFT — FOR REVIEW ONLY · TEMPLATE PENDING CITY HEALTH CONFIRMATION
        </div>

        <div class="line number">
            <span>NUMBER:</span>
            <span class="fill">{{ $value('clinic_number') ?: $record->reference_number }}</span>
        </div>

        <div class="two">
            <div class="line">
                <span>CLINIC:</span>
                <span class="fill">{{ $value('clinic') }}</span>
            </div>

            <div class="line">
                <span>PHILHEALTH NUMBER:</span>
                <span class="fill">{{ $value('philhealth_number') }}</span>
            </div>
        </div>

        <table class="identity">
            <colgroup>
                <col style="width: 19%;">
                <col style="width: 19%;">
                <col style="width: 19%;">
                <col style="width: 8%;">
                <col style="width: 16%;">
                <col style="width: 19%;">
            </colgroup>
            <thead>
                <tr>
                    <th>SURNAME</th>
                    <th>FIRST NAME</th>
                    <th>MIDDLE NAME</th>
                    <th>AGE</th>
                    <th>BIRTHDAY</th>
                    <th>OCCUPATION</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $s['last_name'] }}</td>
                    <td>{{ $s['first_name'] }}</td>
                    <td>{{ $s['middle_name'] ?? '' }}</td>
                    <td>{{ $s['age'] }}</td>
                    <td>{{ \Carbon\Carbon::parse($s['birth_date'])->format('m/d/Y') }}</td>
                    <td>{{ $value('occupation') }}</td>
                </tr>
            </tbody>
        </table>

        <div class="two">
            <div>
                <div class="line">
                    <span>ADDRESS:</span>
                    <span class="fill">{{ $s['address'] }}</span>
                </div>

                <div class="line">
                    <span>HUSBAND'S NAME:</span>
                    <span class="fill">{{ $value('husband_name') }}</span>
                </div>
            </div>

            <div>
                <div class="line">
                    <span>CONTACT NUMBER:</span>
                    <span class="fill">{{ $s['contact_number'] ?? '' }}</span>
                </div>

                <div class="line">
                    <span>OCCUPATION:</span>
                    <span class="fill">{{ $value('husband_occupation') }}</span>
                </div>
            </div>
        </div>

        <section class="section two">
            <div>
                <div class="section-title">PRESENT PREGNANCY:</div>

                <div class="obstetric">
                    GRAVIDA (<span class="inline-value">{{ $value('gravida') }}</span>)
                    PARA (<span class="inline-value">{{ $value('para') }}</span>)
                    F (<span class="inline-value">{{ $value('f') }}</span>)
                    P (<span class="inline-value">{{ $value('p') }}</span>)
                    A (<span class="inline-value">{{ $value('a') }}</span>)
                    L (<span class="inline-value">{{ $value('l') }}</span>)
                </div>

                <div class="line">
                    <span>FETAL DEATH:</span>
                    <span class="fill">{{ $value('fetal_death') }}</span>
                    <span>CAUSE OF DEATH:</span>
                    <span class="fill">{{ $value('cause_of_death') }}</span>
                </div>

                <div class="line">
                    <span>FIRST DAY OF LAST MENSTRUAL PERIOD:</span>
                    <span class="fill">{{ $dateValue('lmp') }}</span>
                </div>

                <div class="line">
                    <span>EXPECTED DATE OF CONFINEMENT:</span>
                    <span class="fill">{{ $dateValue('edc') }}</span>
                </div>

                <div class="line" style="margin-top: 4mm;">
                    <span>TETANUS TOXOID STATUS: SPECIFY</span>
                    <span class="fill">{{ $value('tetanus_status') }}</span>
                </div>
            </div>

            <div>
                <div class="section-title">PAST MEDICAL HISTORY</div>

                <div class="compact">
                    ANY DISEASE/ILLNESS
                    <span>{{ $yesNo('medical_history') }}</span>
                </div>

                <div class="line">
                    <span>SPECIFY:</span>
                    <span class="fill">{{ $value('medical_history_details') }}</span>
                </div>

                <div class="section-title">FAMILY HISTORY:</div>

                <div class="compact">
                    ANY DISEASE/ILLNESS
                    <span>{{ $yesNo('family_history') }}</span>
                </div>

                <div class="line">
                    <span>SPECIFY:</span>
                    <span class="fill">{{ $value('family_history_details') }}</span>
                </div>
            </div>
        </section>

        <section class="section two">
            <div>
                <div class="section-title">
                    PREVIOUS PREGNANCIES COMPLICATED WITH:
                </div>

                <div style="max-width: 55mm;">
                    @foreach ([
                        'hemorrhage' => 'A. HEMORRHAGE',
                        'toxemia' => 'B. TOXEMIA',
                        'placenta_previa' => 'C. PLACENTA PREVIA',
                        'sepsis' => 'D. SEPSIS',
                    ] as $key => $label)
                        <div class="check-row">
                            <span>{{ $label }}</span>
                            <span class="box">{{ $mark($key) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="line">
                    <span>E. OTHERS (Specify)</span>
                    <span class="fill">{{ $value('previous_other') }}</span>
                </div>
            </div>

            <div>
                <div class="section-title">PRESENT ILLNESS:</div>

                <div class="symptoms">
                    @foreach ([
                        'nausea' => 'NAUSEA',
                        'edema' => 'EDEMA',
                        'vomiting' => 'VOMITING',
                        'cramps' => 'CRAMPS',
                        'headache' => 'HEADACHE',
                        'pruritus' => 'PRURITUS',
                        'dizziness' => 'DIZZINESS',
                        'bleeding' => 'BLEEDING',
                    ] as $key => $label)
                        <div class="check-row">
                            <span>{{ $label }}</span>
                            <span class="box">{{ $mark($key) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="line">
                    <span>OTHERS (Specify)</span>
                    <span class="fill">{{ $value('illness_other') }}</span>
                </div>
            </div>
        </section>

        <div class="legend">
            Draft notation: ✓ = Yes; — = No; ? = Not recorded.
            Unselected Yes/No answers mean not recorded.
        </div>

        <section class="section">
            <div class="two">
                <div class="section-title">FAMILY PLANNING</div>
                <div>
                    <strong>HAS FAMILY PLANNING BEEN PRACTICED?</strong>
                    {{ $yesNo('family_planning') }}
                </div>
            </div>

            <div class="two" style="margin-top: 3mm;">
                <div class="line">
                    <span>IF YES, WHAT METHOD/S</span>
                    <span class="fill">{{ $value('family_planning_methods') }}</span>
                </div>

                <div class="line">
                    <span>IF NO, WILLING TO PRACTICE?</span>
                    <span>{{ $yesNo('willing_to_practice') }}</span>
                </div>
            </div>
        </section>

        <div class="visits-title">ANTEPARTUM VISITS</div>

        <table class="visits">
            <colgroup>
                <col style="width: 9%;">
                <col style="width: 10%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 6%;">
                <col style="width: 10%;">
                <col style="width: 16%;">
                <col style="width: 9%;">
                <col style="width: 8%;">
                <col style="width: 22%;">
            </colgroup>
            <thead>
                <tr>
                    <th>DATE</th>
                    <th>Vital<br>Signs</th>
                    <th>HT</th>
                    <th>WT</th>
                    <th>BMI</th>
                    <th>AOG</th>
                    <th>FETAL<br>PRESENTATION</th>
                    <th>Fundic<br>Height</th>
                    <th>FHT</th>
                    <th>REMARKS<br>(Noted signs and symptoms)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($visits as $visit)
                    <tr>
                        <td>{{ $visit->visit_date->format('m/d/Y') }}<br><small>#{{ $visit->id }}</small></td>
                        @foreach ([
                            'vital_signs',
                            'height',
                            'weight',
                            'bmi',
                            'aog',
                            'fetal_presentation',
                            'fundic_height',
                            'fht',
                            'remarks',
                        ] as $field)
                            <td>{{ $visit->details[$field] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach

                @for ($i = 0; $i < $blankRows; $i++)
                    <tr class="blank">
                        @for ($column = 0; $column < 10; $column++)
                            <td>&nbsp;</td>
                        @endfor
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="footer">
            DRAFT · {{ $record->reference_number }}
            · Record date: {{ $record->record_date->format('m/d/Y') }}
            · Age and patient information are captured as of the record date.
            Blank clinical fields mean not recorded.
            @if ($addendums->isNotEmpty())
                <strong>
                    WITH {{ $addendums->count() }} CORRECTION / CLARIFICATION NOTE(S).
                    READ THE ATTACHED PAGE(S) WITH THIS FORM.
                </strong>
            @endif
        </div>

        @if ($addendums->isNotEmpty())
            <section class="appendix">
                <h2 style="font-size: 14pt;">
                    MATERNAL RECORD — CORRECTIONS AND CLARIFICATIONS
                </h2>

                <p>DRAFT — FOR REVIEW ONLY</p>
                <p>
                    Reference: {{ $record->reference_number }}<br>
                    Patient:
                    {{ $s['last_name'] }},
                    {{ $s['first_name'] }}
                    {{ $s['middle_name'] ?? '' }}
                </p>

                <p>
                    These notes accompany the original entries.
                    They do not erase the original record.
                </p>

                @foreach ($addendums as $entry)
                    <article class="note">
                        <p>
                            <strong>{{ $entry->actor_name }}</strong>
                            —
                            {{ \Carbon\Carbon::parse($entry->created_at, config('app.timezone'))
                                ->timezone('Asia/Manila')->format('M d, Y · h:i A') }}
                            (Philippine time)
                        </p>

                        <p><strong>Reason:</strong> {{ $entry->reason }}</p>

                        <div class="note-text">{{ $entry->addendum }}</div>
                    </article>
                @endforeach
            </section>
        @endif
    </main>
</body>
</html>