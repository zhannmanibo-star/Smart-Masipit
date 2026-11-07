@extends('layouts.app')

@section('title', 'Register Resident')
@section('description', 'Create a resident registry record using verified information. An online account is not required.')

@section('actions')
    @if ($forWalkIn)
        <a
            class="button secondary"
            href="{{ route('staff.documents.walk-in.create') }}"
        >
            Back to walk-in request
        </a>
    @else
        <a
            class="button secondary"
            href="{{ route('residents.index') }}"
        >
            Back to registry
        </a>
    @endif
@endsection

@section('content')
    @if ($errors->any())
        <section class="card" role="alert">
            <h2>Please review the following</h2>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card">
        <h2>Resident information</h2>

        <p class="muted">
            Fields marked * are required.
            Check for an existing record before registering a resident.
        </p>

        <p>
            This form creates a resident registry record.
            The resident does not need an online account,
            email address, or password.
        </p>

        @if ($forWalkIn)
            <p class="muted">
                After saving, you will return to the walk-in request
                form with this resident selected.
                Saving this form does not yet submit a document request.
            </p>
        @endif

        <form
            method="POST"
            action="{{ route('residents.store') }}"
        >
            @csrf

            @if ($forWalkIn)
                <input
                    type="hidden"
                    name="return_to"
                    value="walk_in"
                >
            @endif

            @include('residents._form', [
                'resident' => null,
                'buttonText' => $forWalkIn
                    ? 'Save resident and continue'
                    : 'Save resident',
            ])
        </form>
    </section>
@endsection