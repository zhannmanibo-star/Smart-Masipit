<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | SMART MASIPIT</title>

    <style>
        * { box-sizing: border-box; }

        [hidden] { display: none !important; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #eef5f2;
            color: #183b31;
        }

        .card {
            width: 100%;
            max-width: 420px;
            padding: 36px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 40px #183b3114;
        }

        .badge {
            display: inline-block;
            padding: 8px 12px;
            background: #e5f4ec;
            border-radius: 8px;
            font-size: 12px;
            font-weight: bold;
        }

        h1 {
            margin-bottom: 8px;
            font-size: 28px;
        }

        .intro {
            color: #61736c;
            line-height: 1.6;
        }

        label {
            display: block;
            margin: 20px 0 8px;
        }

        .field {
            width: 100%;
            min-width: 0;
            padding: 12px;
            border: 1px solid #b8cac2;
            border-radius: 8px;
            font: inherit;
            font-size: 16px;
        }

        :focus-visible {
            outline: 3px solid #80c9aa;
            outline-offset: 3px;
        }

        .password-field {
            display: flex;
            align-items: stretch;
            gap: 8px;
        }

        .password-field .field {
            flex: 1;
        }

        .password-toggle {
            flex-shrink: 0;
            min-width: 64px;
            padding: 10px;
            border: 1px solid #b8cac2;
            border-radius: 8px;
            background: #f3f8f5;
            color: #176b4c;
            font: inherit;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }

        .password-toggle:hover {
            background: #e5f4ec;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .submit-button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            border: 0;
            border-radius: 8px;
            background: #176b4c;
            color: white;
            font: inherit;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .submit-button:hover {
            background: #105237;
        }

        .error,
        .success {
            padding: 12px;
            border-radius: 8px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .error {
            background: #fff0f0;
            color: #a12525;
        }

        .success {
            background: #dcf4e7;
        }

        a { color: #176b4c; }

        .back-link {
            margin: 0 0 24px;
        }

        .back-link a {
            text-decoration: none;
        }

        .forgot-link {
            margin-top: 20px;
            text-align: center;
        }

        .register-link {
            margin-top: 24px;
            text-align: center;
        }

        @media (max-width: 400px) {
            body { padding: 16px; }
            .card { padding: 24px; }
        }
    </style>
</head>

<body>
    <main class="card">
        <p class="back-link">
            <a href="{{ route('home') }}">
                ← Back to home
            </a>
        </p>

        <span class="badge">BARANGAY MASIPIT</span>

        <h1>SMART MASIPIT</h1>

        <p class="intro">
            Sign in to access your barangay services.
        </p>

        @if ($errors->any())
            <div class="error" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('status'))
            <p class="success" role="status">
                {{ session('status') }}
            </p>
        @endif

        <form
            id="login-form"
            method="POST"
            action="{{ route('login.store') }}"
        >
            @csrf

            <label for="email">Email address</label>

            <input
                class="field"
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                autofocus
            >

            <label for="password">Password</label>

            <div class="password-field">
                <input
                    class="field"
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

                <button
                    class="password-toggle"
                    id="toggle-password"
                    type="button"
                    aria-controls="password"
                    aria-label="Show password"
                    hidden
                >
                    Show
                </button>
            </div>

            <label class="remember">
                <input type="checkbox" name="remember" value="1">
                Remember me
            </label>

            <button class="submit-button" type="submit">
                Sign in
            </button>

            <p class="forgot-link">
                <a href="{{ route('password.request') }}">
                    Forgot your password?
                </a>
            </p>
        </form>

        @if (Route::has('register'))
            <p class="register-link">
                New resident user?
                <a href="{{ route('register') }}">
                    Create an account
                </a>
            </p>
        @endif
    </main>

    <script>
        const passwordInput = document.getElementById('password');
        const toggleButton = document.getElementById('toggle-password');
        const loginForm = document.getElementById('login-form');

        function hidePassword() {
            passwordInput.type = 'password';
            toggleButton.textContent = 'Show';
            toggleButton.setAttribute('aria-label', 'Show password');
        }

        toggleButton.hidden = false;

        toggleButton.addEventListener('click', function () {
            const shouldShow = passwordInput.type === 'password';

            passwordInput.type = shouldShow ? 'text' : 'password';
            toggleButton.textContent = shouldShow ? 'Hide' : 'Show';
            toggleButton.setAttribute(
                'aria-label',
                shouldShow ? 'Hide password' : 'Show password'
            );
        });

        loginForm.addEventListener('submit', hidePassword);

        window.addEventListener('pageshow', hidePassword);
    </script>
</body>
</html>