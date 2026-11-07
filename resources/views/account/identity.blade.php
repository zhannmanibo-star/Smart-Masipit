<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Identity Evidence | SMART MASIPIT</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            background: #f4f6f8;
            color: #20312c;
            font: 16px/1.6 "Segoe UI", Arial, sans-serif;
        }

        main {
            max-width: 760px;
            margin: auto;
            padding: 28px;
            background: white;
            border: 1px solid #dfe5e9;
            border-radius: 14px;
        }

        .muted { color: #60716b; }

        .errors {
            padding: 16px;
            background: #fff2f1;
            color: #922c27;
            border-radius: 8px;
        }

        button {
            padding: 12px 18px;
            border: 0;
            border-radius: 7px;
            background: #16634b;
            color: white;
            cursor: pointer;
            font: inherit;
        }

        a { color: #16634b; }

        :focus-visible {
            outline: 3px solid #d4a843;
            outline-offset: 3px;
        }
    </style>
</head>
<body>
<main>
    <a href="{{ route('account.status') }}">Back to account status</a>

    <h1>Submit ID and camera selfie</h1>

    <p>Hello, {{ auth()->user()->name }}.</p>

    @if ($application->hasIdentityEvidence())
        <p>
            You have already submitted identity images.
            Submitting this form will replace them while your
            application is still pending.
        </p>
    @else
        <p>
            Complete this step so the Secretary or Captain can
            review your account application.
        </p>
    @endif

    @if ($errors->any())
        <div class="errors" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('account.identity.store') }}"
        enctype="multipart/form-data"
    >
        @csrf

        @include('auth.partials.identity-fields')

        <button type="submit">Submit ID and selfie</button>
    </form>
</main>
</body>
</html>