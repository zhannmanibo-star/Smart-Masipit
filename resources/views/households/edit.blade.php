@extends('layouts.app')

@section('title', 'Edit Household Location')
@section('description', $household->household_number)

@section('actions')
    <a
        class="button secondary"
        href="{{ route('households.show', $household) }}"
    >
        Back to household
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>Household location</h2>

        <p class="muted">
            This updates the household location only.
            Resident profile addresses and previously prepared
            certificates retain their saved details.
        </p>

        <form
            method="POST"
            action="{{ route('households.update', $household) }}"
            onsubmit="return confirm('Save this household location change and its reason?');"
        >
            @csrf
            @method('PATCH')

            <input
                type="hidden"
                name="location_version"
                value="{{ old('location_version', $household->location_version) }}"
            >

            <div class="grid">
                <div>
                    <label for="sitio">Sitio *</label>

                    <input
                        id="sitio"
                        name="sitio"
                        value="{{ old('sitio', $household->sitio) }}"
                        maxlength="100"
                        required
                    >
                </div>

                <div>
                    <label for="address">House / Street / Purok</label>

                    <input
                        id="address"
                        name="address"
                        value="{{ old('address', $household->address) }}"
                        maxlength="255"
                    >
                </div>
            </div>

            <div style="margin-top: 20px;">
                <label for="reason">Reason for change *</label>

                <textarea
                    id="reason"
                    name="reason"
                    rows="4"
                    minlength="10"
                    maxlength="1000"
                    required
                    style="width: 100%; padding: 12px; font: inherit;"
                >{{ old('reason') }}</textarea>
            </div>

            <label style="display: flex; gap: 10px; margin-top: 18px;">
                <input
                    type="checkbox"
                    name="details_confirmed"
                    value="1"
                    style="width: auto;"
                    required
                >

                <span>
                    I verified the corrected household location
                    and recorded the reason for this change.
                </span>
            </label>

            <button type="submit">Save location changes</button>
        </form>
    </section>
@endsection