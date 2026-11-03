@extends('layouts.app')

@section('title', 'Register Resident')
@section('description', 'Enter the resident’s details as verified against available records.')

@section('actions')
    <a class="button secondary" href="{{ route('residents.index') }}">
        Back to registry
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>Resident information</h2>

        <p class="muted">
            Fields marked * are required.
            Check for an existing record before registering a resident.
        </p>

        <form method="POST" action="{{ route('residents.store') }}">
            @csrf

            @include('residents._form', [
                'resident' => null,
                'buttonText' => 'Save resident',
            ])
        </form>
    </section>
@endsection