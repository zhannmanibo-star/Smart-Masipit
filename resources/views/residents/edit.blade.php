@extends('layouts.app')

@section('title', 'Edit Resident')
@section('description', 'Review and update the resident’s recorded information.')

@section('actions')
    <a class="button secondary" href="{{ route('residents.index') }}">
        Back to registry
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>Resident information</h2>

        <p class="muted">
            Resident reference:
            <span class="reference">
                {{ $resident->resident_number }}
            </span>
        </p>

        <p class="muted">Fields marked * are required.</p>

        <form method="POST"
              action="{{ route('residents.update', $resident) }}">
            @csrf
            @method('PATCH')

            @include('residents._form', [
                'resident' => $resident,
                'buttonText' => 'Save changes',
            ])
        </form>
    </section>
@endsection