@extends('auth.password-layout')

@section('title', 'Reset password')

@section('content')
    <p class="muted">
        Enter your account email and choose a new password.
    </p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <label for="email">Email address</label>

        <input
            class="field"
            id="email"
            type="email"
            name="email"
            value="{{ old('email', $email) }}"
            autocomplete="email"
            maxlength="255"
            required
        >

        <label for="password">New password</label>

        <input
            class="field"
            id="password"
            type="password"
            name="password"
            autocomplete="new-password"
            required
        >

        <label for="password_confirmation">Confirm new password</label>

        <input
            class="field"
            id="password_confirmation"
            type="password"
            name="password_confirmation"
            autocomplete="new-password"
            required
        >

        <label class="toggle" for="show-passwords" hidden>
            <input
                id="show-passwords"
                type="checkbox"
                aria-controls="password password_confirmation"
            >
            Show passwords
        </label>

        <button type="submit">
            Reset password
        </button>
    </form>
@endsection

@section('scripts')
    <script>
        const toggle = document.getElementById('show-passwords');
        const password = document.getElementById('password');
        const confirmation = document.getElementById('password_confirmation');

        toggle.closest('label').hidden = false;

        toggle.addEventListener('change', function () {
            const type = this.checked ? 'text' : 'password';

            password.type = type;
            confirmation.type = type;
        });
    </script>
@endsection