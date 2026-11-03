@extends('auth.password-layout')

@section('title', 'Forgot password')

@section('content')
    <p class="muted">
        Enter the email address associated with your SMART MASIPIT
        account to request a password reset link.
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <label for="email">Email address</label>

        <input
            class="field"
            id="email"
            type="email"
            name="email"
            value="{{ old('email') }}"
            autocomplete="email"
            maxlength="255"
            required
            autofocus
        >

        <button type="submit">
            Send password reset link
        </button>
    </form>

    <p class="muted">
        Check your inbox and Spam folder after submitting.
    </p>
@endsection