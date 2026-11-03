@extends('layouts.app')

@section('title', 'Administration')
@section('description', 'Manage the administrative tools available to your account.')

@section('actions')
    <a class="button secondary" href="{{ route('dashboard') }}">
        Return to dashboard
    </a>
@endsection

@section('content')
    <section class="card">
        <h2>Welcome, {{ auth()->user()->name }}</h2>
        <p>Your administrator account is active.</p>
    </section>

    @can('access-admin')
        <section class="card">
            <h2>Email Monitoring</h2>

            <p>
                Review failed account-verification and document-pickup
                email jobs, and request another delivery attempt.
            </p>

            <a
                class="button"
                href="{{ route('admin.email-monitoring.index') }}"
            >
                Open email monitoring
            </a>
        </section>
    @endcan

    <section class="card">
        <h2>Account session</h2>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Sign out</button>
        </form>
    </section>
@endsection