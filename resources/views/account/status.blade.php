<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Status | SMART MASIPIT</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f4f6f8;
            color: #20312c;
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
        }

        main {
            width: 100%;
            max-width: 600px;
            padding: 36px;
            background: white;
            border: 1px solid #dfe5e9;
            border-radius: 14px;
        }

        .brand {
            color: #16634b;
            font-weight: 750;
            letter-spacing: 1px;
        }

        .muted { color: #60716b; }

        h1 {
            margin-bottom: 12px;
            font-size: 27px;
            line-height: 1.3;
        }

        .notice {
            margin: 24px 0;
            padding: 18px;
            border-radius: 8px;
            background: #f6f8f7;
            border: 1px solid #dfe5e9;
        }

        .reason { white-space: pre-wrap; }

        button {
            padding: 12px 20px;
            border: 0;
            border-radius: 7px;
            background: #16634b;
            color: white;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        button:hover { background: #103f33; }

        button:focus-visible {
            outline: 3px solid #d4a843;
            outline-offset: 3px;
        }
    </style>
</head>
<body>
<main>
    <div class="brand">SMART MASIPIT</div>
    <div class="muted">Barangay Masipit · Calapan City</div>

    @php
        $user = auth()->user();
    @endphp

    @if ($user->account_status === 'pending')
        <h1>Account verification pending</h1>

        <p>Hello, {{ $user->name }}.</p>

        @if ($user->role === 'resident')
            <p>
                Your account requires verification by the Barangay
                Secretary or Barangay Captain before you can use
                resident services.
            </p>
        @else
            <p>
                Your staff account is awaiting activation.
                Please contact the system administrator.
            </p>
        @endif

    @elseif ($user->account_status === 'rejected')
        <h1>Account application not approved</h1>

        <p>
            Please contact the barangay office to clarify your
            application details.
        </p>

        @if ($user->rejection_reason)
            <div class="notice">
                <strong>Reason</strong>
                <div class="reason">{{ $user->rejection_reason }}</div>
            </div>
        @endif

    @elseif ($user->account_status === 'suspended')
        <h1>Account access suspended</h1>

        <p>
            Please contact the barangay office regarding your account.
        </p>

    @else
        <h1>Account review required</h1>

        <p>
            Your account is not currently eligible to access services.
            Please contact the barangay office to review your account
            and linked resident record.
        </p>
    @endif

    <div class="notice">
        <strong>Account email</strong>
        <div>{{ $user->email }}</div>
    </div>

    <p class="muted">
        You may sign in again to check your account status.
    </p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>