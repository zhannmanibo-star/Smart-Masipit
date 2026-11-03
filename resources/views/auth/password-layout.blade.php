<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">

    <title>@yield('title') | SMART MASIPIT</title>

    <style>
        * { box-sizing: border-box; }

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
            max-width: 460px;
            padding: 32px;
            border-radius: 18px;
            background: white;
            box-shadow: 0 12px 40px #183b3114;
        }

        .badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 8px;
            background: #e5f4ec;
            font-size: 12px;
            font-weight: bold;
        }

        h1 { font-size: 26px; margin-bottom: 12px; }

        p { line-height: 1.6; }

        .muted { color: #61736c; }

        label {
            display: block;
            margin: 20px 0 8px;
            font-weight: bold;
        }

        .field {
            width: 100%;
            padding: 12px;
            border: 1px solid #b8cac2;
            border-radius: 8px;
            font: inherit;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            border: 0;
            border-radius: 8px;
            background: #176b4c;
            color: white;
            font: inherit;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover { background: #105237; }

        a { color: #176b4c; }

        :focus-visible {
            outline: 3px solid #80c9aa;
            outline-offset: 3px;
        }

        .success, .errors {
            padding: 14px;
            border-radius: 8px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .success { background: #dcf4e7; }
        .errors { background: #fff0f0; color: #a12525; }
        .errors ul { margin: 8px 0 0; padding-left: 20px; }

        .toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <main class="card">
        <span class="badge">SMART MASIPIT</span>

        <h1>@yield('title')</h1>

        @if (session('status'))
            <p class="success" role="status">
                {{ session('status') }}
            </p>
        @endif

        @if ($errors->any())
            <div class="errors" role="alert">
                <strong>Please check the following:</strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')

        <p style="margin-top: 24px;">
            <a href="{{ route('login') }}">Back to login</a>
        </p>
    </main>

    @yield('scripts')
</body>
</html>