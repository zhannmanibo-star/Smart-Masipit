@extends('layouts.app')

@section('title', 'Household Details')
@section('description', $household->household_number)

@section('actions')
    <div class="row-actions">
        <a class="button secondary"
           href="{{ route('households.members.manage', $household) }}">
            Manage members
        </a>

        <a class="button secondary"
           href="{{ route('households.index') }}">
            Back to households
        </a>

        <a class="button"
           href="{{ route('households.edit', $household) }}">
            Edit location
        </a>

        <a class="button secondary"
           href="{{ route('households.history', $household) }}">
            Change history
        </a>
    </div>
@endsection

@section('content')
    @if ($household->needsHeadReplacement())
        <section class="card" role="alert">
            <h2>Household head needs replacement</h2>

            <p>
                The recorded head is deceased, archived, or unavailable.
                Assign an eligible existing member as the replacement
                before adding new members.
            </p>

            <p class="muted">
                Existing memberships and history are retained.
            </p>

            <a class="button"
               href="{{ route('households.members.manage', $household) }}">
                Manage members
            </a>
        </section>
    @endif

    <section class="card">
        <h2>{{ $household->household_number }}</h2>

        <p>
            <strong>Recorded head:</strong>

            @if ($household->head)
                {{ $household->head->first_name }}
                {{ $household->head->middle_name }}
                {{ $household->head->last_name }}
            @else
                Unavailable
            @endif
        </p>

        @if ($household->head)
            @if ($household->head->isDeceased())
                <p>
                    <strong>Deceased</strong>
                    — {{ $household->head->date_of_death->format('M d, Y') }}
                </p>
            @endif

            @if ($household->head->trashed())
                <p class="muted">The head's registry record is archived.</p>
            @endif
        @endif

        <p>
            <strong>Sitio:</strong>
            {{ $household->sitio }}
        </p>

        <p>
            <strong>House / Street / Purok:</strong>
            {{ $household->address ?: 'Not recorded' }}
        </p>

        <p>
            <strong>Active members:</strong>
            {{ $household->active_memberships_count }}
        </p>

        <p class="muted">
            Active members exclude archived records and residents
            with a recorded death.
        </p>
    </section>

    <section class="card">
        <h2>Household members</h2>

        <p class="muted">
            Deceased and archived members remain visible for reference.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Resident</th>
                        <th scope="col">Relationship to head</th>
                        <th scope="col">Birth date</th>
                        <th scope="col">Registry status</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($memberships as $membership)
                        @php
                            $member = $membership->resident;
                        @endphp

                        <tr>
                            <td>
                                @if ($member)
                                    <strong>
                                        {{ $member->first_name }}
                                        {{ $member->middle_name }}
                                        {{ $member->last_name }}
                                    </strong>

                                    <div class="reference muted">
                                        {{ $member->resident_number }}
                                    </div>
                                @else
                                    Resident record unavailable
                                @endif
                            </td>

                            <td>
                                {{ \App\Models\HouseholdMember::RELATIONSHIPS[
                                    $membership->relationship_to_head
                                ] ?? $membership->relationship_to_head }}
                            </td>

                            <td>
                                {{ $member?->birth_date?->format('M d, Y')
                                    ?? 'Not recorded' }}
                            </td>

                            <td>
                                @if (! $member)
                                    Unavailable
                                @else
                                    @if ($member->isDeceased())
                                        <strong>Deceased</strong>

                                        <div class="muted">
                                            {{ $member->date_of_death->format('M d, Y') }}
                                        </div>
                                    @endif

                                    @if ($member->trashed())
                                        <div>Archived</div>
                                    @elseif (! $member->isDeceased())
                                        <strong>Active</strong>
                                        <div class="muted">No recorded death</div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">
                                No household members recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('households._pagination', [
            'records' => $memberships,
            'paginationLabel' => 'Household members',
        ])
    </section>

    @if (! $household->needsHeadReplacement())
        <section class="card">
            <h2>Find a member to add</h2>

            <form method="GET"
                  action="{{ route('households.show', $household) }}"
                  class="toolbar"
                  role="search">
                <div class="search-field">
                    <label for="search">Search available residents</label>

                    <input id="search"
                           type="search"
                           name="search"
                           value="{{ $search }}"
                           maxlength="100"
                           placeholder="Name, sitio, or resident reference">
                </div>

                <button type="submit">Search</button>

                <a class="button secondary"
                   href="{{ route('households.show', $household) }}">
                    Clear
                </a>
            </form>

            <p class="muted">
                Results include unarchived residents without a recorded
                death or an existing household assignment.
                Adding a member does not change their resident profile address.
            </p>
        </section>

        <section class="card">
            <h2>Add household member</h2>

            <form method="POST"
                  action="{{ route('households.members.store', $household) }}">
                @csrf

                @include('households._resident-picker')

                <label for="relationship_to_head">
                    Relationship to household head *
                </label>

                <select id="relationship_to_head"
                        name="relationship_to_head"
                        required>
                    <option value="">Select relationship</option>

                    @foreach ($relationships as $value => $label)
                        <option value="{{ $value }}"
                            @selected(old('relationship_to_head') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <label style="display: flex; gap: 10px; margin-top: 18px;">
                    <input type="checkbox"
                           name="details_confirmed"
                           value="1"
                           style="width: auto;"
                           required>

                    <span>
                        I verified that this resident belongs to this
                        household and confirmed their relationship to the head.
                    </span>
                </label>

                <button type="submit" @disabled($residents->isEmpty())>
                    Add member
                </button>
            </form>
        </section>
    @endif
@endsection