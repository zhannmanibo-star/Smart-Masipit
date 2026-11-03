@extends('layouts.app')

@section('title', 'Manage Household Members')
@section('description', $household->household_number)

@section('actions')
    <a class="button secondary"
       href="{{ route('households.show', $household) }}">
        Back to household
    </a>
@endsection

@section('content')
    @php
        $eligibleHeads = $memberships->filter(function ($membership) use ($household) {
            $resident = $membership->resident;

            return $resident
                && ! $resident->trashed()
                && ! $resident->isDeceased()
                && (int) $membership->resident_id !== (int) $household->head_resident_id;
        });

        $transferableMembers = $memberships->filter(function ($membership) use ($household) {
            $resident = $membership->resident;

            return $resident
                && ! $resident->trashed()
                && ! $resident->isDeceased()
                && (int) $membership->resident_id !== (int) $household->head_resident_id
                && $membership->relationship_to_head !== 'head';
        });
    @endphp

    @if ($household->needsHeadReplacement())
        <section class="card" role="alert">
            <h2>Replacement head required</h2>

            <p>
                The recorded head is deceased, archived, or unavailable.
                Select an eligible existing member as the new head.
                Existing memberships and history will be retained.
            </p>
        </section>
    @endif

    <section class="card">
        <h2>Change household head</h2>

        <p>
            Select exactly one eligible replacement as “Head of household”.
            Review every other member's relationship to that new head,
            including the former head.
        </p>

        <p class="muted">
            Deceased and archived members remain in the relationship review,
            but cannot be selected as the new head.
        </p>

        @if ($eligibleHeads->isEmpty())
            <p class="errors" role="alert">
                No eligible replacement head is available among the existing
                members. A new head cannot be assigned through this form.
                Refer the household to the Secretary or Captain for review.
            </p>
        @endif

        <form method="POST"
              action="{{ route('households.head.update', $household) }}"
              onsubmit="return confirm('Save the new head and all reviewed relationships?');">
            @csrf
            @method('PATCH')

            <input type="hidden"
                   name="membership_fingerprint"
                   value="{{ old('membership_fingerprint', $membershipFingerprint) }}">

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Member</th>
                            <th scope="col">Current relationship</th>
                            <th scope="col">Relationship to new head *</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($memberships as $membership)
                            @php
                                $member = $membership->resident;

                                $canBeNewHead = $member
                                    && ! $member->trashed()
                                    && ! $member->isDeceased()
                                    && (int) $membership->resident_id !== (int) $household->head_resident_id;
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

                                        @if ($member->isDeceased())
                                            <div>
                                                <strong>Deceased</strong>
                                                — {{ $member->date_of_death->format('M d, Y') }}
                                            </div>
                                        @endif

                                        @if ($member->trashed())
                                            <div class="muted">Archived record</div>
                                        @endif

                                        @if ((int) $membership->resident_id === (int) $household->head_resident_id)
                                            <div class="muted">
                                                Currently recorded household head
                                            </div>
                                        @endif
                                    @else
                                        Resident record unavailable
                                    @endif
                                </td>

                                <td>
                                    {{ $relationships[$membership->relationship_to_head]
                                        ?? $membership->relationship_to_head }}
                                </td>

                                <td>
                                    <label for="relationship_{{ $membership->id }}">
                                        New relationship
                                    </label>

                                    <select id="relationship_{{ $membership->id }}"
                                            name="relationships[{{ $membership->id }}]"
                                            required>
                                        <option value="">Select</option>

                                        @foreach ($relationships as $value => $label)
                                            @if ($value !== 'head' || $canBeNewHead)
                                                <option value="{{ $value }}"
                                                    @selected(old('relationships.' . $membership->id) === $value)>
                                                    {{ $label }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>

                                    @if (! $canBeNewHead)
                                        <div class="muted">
                                            Select a non-head relationship.
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="empty">
                                    No household members recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <label for="head_reason" style="margin-top: 18px;">
                Reason for changing head *
            </label>

            <textarea id="head_reason"
                      name="reason"
                      rows="3"
                      minlength="10"
                      maxlength="1000"
                      style="width: 100%; padding: 12px; font: inherit;"
                      required>{{ old('membership_id') === null ? old('reason') : '' }}</textarea>

            <label style="display: flex; gap: 10px; margin-top: 16px;">
                <input type="checkbox"
                       name="details_confirmed"
                       value="1"
                       style="width: auto;"
                       required>

                <span>
                    I verified the new head and every member's
                    relationship to that person.
                </span>
            </label>

            <button type="submit" @disabled($eligibleHeads->isEmpty())>
                Save new household head
            </button>
        </form>
    </section>

    <section class="card">
        <h2>Transfer a member</h2>

        <p>
            Open the destination household first and verify its head
            and location. Copy its exact household number below.
        </p>

        <p>
            <a href="{{ route('households.index') }}"
               target="_blank"
               rel="noopener">
                Find destination household in a new tab
            </a>
        </p>

        <p class="muted">
            Only unarchived members without a recorded death can be
            transferred. The current head must be replaced before transfer.
            The destination household must also have an eligible head.
        </p>

        <p class="muted">
            This changes household membership only. Resident profile
            addresses and prepared certificates retain their saved details.
        </p>

        @if ($transferableMembers->isEmpty())
            <p role="status">
                No eligible members are available for transfer.
            </p>
        @else
            <form method="POST"
                  action="{{ route('households.members.transfer', $household) }}"
                  onsubmit="return confirm('Transfer this member to the specified household?');">
                @csrf

                <input type="hidden"
                       name="membership_fingerprint"
                       value="{{ old('membership_fingerprint', $membershipFingerprint) }}">

                <label for="membership_id">Member to transfer *</label>

                <select id="membership_id" name="membership_id" required>
                    <option value="">Select a member</option>

                    @foreach ($transferableMembers as $membership)
                        <option value="{{ $membership->id }}"
                            @selected((string) old('membership_id') === (string) $membership->id)>
                            {{ $membership->resident->last_name }},
                            {{ $membership->resident->first_name }}
                            {{ $membership->resident->middle_name }}
                            — {{ $membership->resident->resident_number }}
                        </option>
                    @endforeach
                </select>

                <label for="destination_number" style="margin-top: 16px;">
                    Destination household number *
                </label>

                <input id="destination_number"
                       name="destination_number"
                       value="{{ old('destination_number') }}"
                       maxlength="40"
                       placeholder="HH-..."
                       required>

                <label for="transfer_relationship" style="margin-top: 16px;">
                    Relationship to destination household head *
                </label>

                <select id="transfer_relationship"
                        name="relationship_to_head"
                        required>
                    <option value="">Select relationship</option>

                    @foreach ($relationships as $value => $label)
                        @if ($value !== 'head')
                            <option value="{{ $value }}"
                                @selected(old('relationship_to_head') === $value)>
                                {{ $label }}
                            </option>
                        @endif
                    @endforeach
                </select>

                <label for="transfer_reason" style="margin-top: 16px;">
                    Reason for transfer *
                </label>

                <textarea id="transfer_reason"
                          name="reason"
                          rows="3"
                          minlength="10"
                          maxlength="1000"
                          style="width: 100%; padding: 12px; font: inherit;"
                          required>{{ old('membership_id') !== null ? old('reason') : '' }}</textarea>

                <label style="display: flex; gap: 10px; margin-top: 16px;">
                    <input type="checkbox"
                           name="details_confirmed"
                           value="1"
                           style="width: auto;"
                           required>

                    <span>
                        I verified the destination household, its current
                        head, and the member's relationship to that head.
                    </span>
                </label>

                <button type="submit">Transfer member</button>
            </form>
        @endif
    </section>
@endsection