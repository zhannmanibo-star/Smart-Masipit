@extends('layouts.app')

@section('title', 'Resident Census Summary')
@section('description', 'View demographic and household counts from the current barangay registry.')

@section('actions')
    <div class="row-actions">
        <a class="button secondary"
           href="{{ route('dashboard') }}">
            Dashboard
        </a>

        <a class="button"
   href="{{ route('census.print') }}"
   target="_blank"
   rel="noopener">
    Print / Save as PDF
</a>

        <a class="button secondary"
           href="{{ route('residents.index') }}">
            Resident registry
        </a>

        <a class="button"
           href="{{ route('census.index') }}">
            Refresh summary
        </a>
    </div>
@endsection

@section('content')
    <section class="card">
        <h2>Barangay Masipit registry summary</h2>

        <p>
            Generated:
            <strong>
                {{ $generatedAt->format('M d, Y · h:i:s A') }}
            </strong>
            · Philippine time
        </p>

        <p class="muted">
            This summarizes records currently saved in SMART MASIPIT.
            It is not an official population census or a reconstruction
            of the registry on a previous date.
        </p>

        <p class="muted">
            Demographic tables include unarchived residents without
            a recorded death. Age is calculated using the generation date.
            Health records are not included.
        </p>
    </section>

    <section class="card">
        <h2>Resident record counts</h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 16px;">
            @foreach ([
                [
                    'label' => 'Active registry residents',
                    'count' => $activeCount,
                    'description' => 'Unarchived records without a recorded death.',
                ],
                [
                    'label' => 'Recorded deceased',
                    'count' => $deceasedCount,
                    'description' => 'Unarchived records with a recorded death.',
                ],
                [
                    'label' => 'Archived resident records',
                    'count' => $archivedCount,
                    'description' => 'All archived records, including any recorded deceased.',
                ],
                [
                    'label' => 'Total retained resident records',
                    'count' => $totalRegistryCount,
                    'description' => 'The sum of the three separate record groups.',
                ],
            ] as $card)
                <div style="padding: 20px; border: 1px solid #d4e2da; border-radius: 12px; background: #f5faf7;">
                    <strong>{{ $card['label'] }}</strong>

                    <div style="font-size: 36px; font-weight: bold; margin: 12px 0;">
                        {{ number_format($card['count']) }}
                    </div>

                    <p class="muted">{{ $card['description'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    @php
        $demographicTables = [
            [
                'title' => 'Residents by sitio',
                'column' => 'Sitio',
                'rows' => $sitioCounts,
                'note' => 'Uses the sitio saved in each resident profile. Different spellings remain separate.',
            ],
            [
                'title' => 'Residents by age group',
                'column' => 'Age group',
                'rows' => $ageCounts,
                'note' => 'Age groups are reporting ranges for this system. Invalid or future birth dates are listed for review.',
            ],
            [
                'title' => 'Residents by recorded sex',
                'column' => 'Recorded sex',
                'rows' => $sexCounts,
                'note' => 'Uses the value recorded in the resident profile. Missing or unexpected values are grouped separately.',
            ],
        ];
    @endphp

    @foreach ($demographicTables as $table)
        <section class="card">
            <h2>{{ $table['title'] }}</h2>

            <p class="muted">{{ $table['note'] }}</p>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">{{ $table['column'] }}</th>
                            <th scope="col">Residents</th>
                            <th scope="col">Share of active registry</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($table['rows'] as $label => $count)
                            <tr>
                                <th scope="row">{{ $label }}</th>

                                <td>{{ number_format($count) }}</td>

                                <td>
                                    @if ($activeCount > 0)
                                        {{ number_format(
                                            ($count / $activeCount) * 100,
                                            1
                                        ) }}%
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="empty">
                                    No active resident records available.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    <tfoot>
                        <tr>
                            <th scope="row">Total</th>

                            <td>
                                <strong>
                                    {{ number_format($activeCount) }}
                                </strong>
                            </td>

                            <td>
                                {{ $activeCount > 0 ? '100.0%' : '—' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="muted">
                Percentages are rounded and may not add up to exactly 100%.
            </p>
        </section>
    @endforeach

    <section class="card">
        <h2>Household records</h2>

        <p class="muted">
            Household counts are based on saved household records.
            An active member is an unarchived resident without a recorded death.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Measure</th>
                        <th scope="col">Count</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <th scope="row">Total household records</th>
                        <td>{{ number_format($householdCount) }}</td>
                    </tr>

                    <tr>
                        <th scope="row">
                            Households with at least one active member
                        </th>
                        <td>
                            {{ number_format($householdsWithActiveMembers) }}
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            Households without active members
                        </th>
                        <td>
                            {{ number_format($householdsWithoutActiveMembers) }}
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            Households needing head review
                        </th>
                        <td>
                            {{ number_format($householdsNeedingHeadReview) }}
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            Active residents assigned to a household
                        </th>
                        <td>
                            {{ number_format($activeResidentsWithHousehold) }}
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            Active residents without a household assignment
                        </th>
                        <td>
                            {{ number_format($activeResidentsWithoutHousehold) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="muted">
            Head review is needed when the recorded head is missing,
            archived, or deceased. This count can overlap with the
            other household categories.
        </p>

        <a class="button secondary"
           href="{{ route('households.index') }}">
            Open household registry
        </a>
    </section>

    @if (
        $ageCounts['Birth date needs review'] > 0
        || $sexCounts['Other / not recorded'] > 0
        || ($sitioCounts['Not recorded'] ?? 0) > 0
    )
        <section class="card" role="status">
            <h2>Resident information needs review</h2>

            <p>
                Some active records have missing or unexpected information.
                They remain included in the total and are shown separately
                in the demographic tables.
            </p>

            <a class="button secondary"
               href="{{ route('residents.index') }}">
                Review resident registry
            </a>
        </section>
    @endif
@endsection