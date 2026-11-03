@extends('layouts.app')

@section('title', 'Create Household')
@section('description', 'Choose the head from the existing resident registry.')

@section('actions')
    <a class="button secondary" href="{{ route('households.index') }}">
        Back to households
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>Find the head of household</h2>

        <form
            method="GET"
            action="{{ route('households.create') }}"
            class="toolbar"
            role="search"
        >
            <div class="search-field">
                <label for="search">Search available residents</label>

                <input
                    id="search"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Name, sitio, or resident reference"
                >
            </div>

            <button type="submit">Search</button>

            <a class="button secondary" href="{{ route('households.create') }}">
                Clear
            </a>
        </form>

        <p class="muted">
            Only active residents without an existing household appear.
            Complete your search before filling out the form below.
        </p>
    </section>

    <section class="card">
        <form method="POST" action="{{ route('households.store') }}">
            @csrf

            <h2>1. Select the head</h2>

            @include('households._resident-picker')

            <h2>2. Household location</h2>

            <div class="grid">
                <div>
                    <label for="sitio">Sitio *</label>
                    <input
                        id="sitio"
                        name="sitio"
                        value="{{ old('sitio') }}"
                        maxlength="100"
                        required
                    >
                </div>

                <div>
                    <label for="address">House / Street / Purok</label>
                    <input
                        id="address"
                        name="address"
                        value="{{ old('address') }}"
                        maxlength="255"
                    >
                </div>
            </div>

            <p class="muted">
                Barangay Masipit, Calapan City, Oriental Mindoro.
                The system will assign a unique household number.
            </p>

            <label style="display: flex; gap: 10px; margin-top: 18px;">
                <input
                    type="checkbox"
                    name="details_confirmed"
                    value="1"
                    style="width: auto;"
                    required
                >

                <span>
                    I verified the selected head and household location.
                </span>
            </label>

            <button type="submit" @disabled($residents->isEmpty())>
                Create household
            </button>
        </form>
    </section>
@endsection