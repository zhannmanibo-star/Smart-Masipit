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
            max-width: 640px;
            padding: 36px;
            background: white;
            border: 1px solid #dfe5e9;
            border-radius: 14px;
        }

        .brand { color: #16634b; font-weight: 750; letter-spacing: 1px; }
        .muted { color: #60716b; }
        h1 { font-size: 27px; line-height: 1.3; }

        .notice {
            margin: 24px 0;
            padding: 18px;
            border-radius: 8px;
            background: #f6f8f7;
            border: 1px solid #dfe5e9;
        }

        .reason { white-space: pre-wrap; }

        button, .button {
            display: inline-block;
            padding: 12px 20px;
            border: 0;
            border-radius: 7px;
            background: #16634b;
            color: white;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        :focus-visible {
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
        $application = $user->role === 'resident'
            ? $user->residentApplication()->first()
            : null;
    @endphp

    @if (session('success'))
        <div class="notice" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($user->account_status === 'pending')
        <h1>Account verification pending</h1>

        <p>Hello, {{ $user->name }}.</p>

        @if ($user->role === 'resident')
            <p>
                Your account requires verification by the Barangay
                Secretary or Captain before you can use resident services.
            </p>

            @if ($application)
                <div class="notice">
                    @if ($application->hasIdentityEvidence())
                        <strong>ID and selfie submitted</strong>
                        <p>
                            Your images are awaiting review.
                            If you submitted the wrong or unclear images,
                            you may replace them while your application is pending.
                        </p>
                    @else
                        <strong>ID and camera selfie required</strong>
                        <p>
                            Submit your identity images to complete
                            your application for review.
                        </p>
                    @endif

                    <a
                        class="button"
                        href="{{ route('account.identity.edit') }}"
                    >
                        {{ $application->hasIdentityEvidence()
                            ? 'Replace ID and selfie'
                            : 'Submit ID and selfie' }}
                    </a>
                </div>
            @else
                <p>
                    Your application record could not be found.
                    Contact the barangay office.
                </p>
            @endif
        @else
            <p>
                Your staff account is awaiting activation.
                Please contact the system administrator.
            </p>
        @endif

    @elseif ($user->account_status === 'rejected')
        <h1>Account application not approved</h1>

        <p>
            Please contact the barangay office to clarify
            your application details.
        </p>

        @if ($user->rejection_reason)
            <div class="notice">
                <strong>Reason</strong>
                <div class="reason">{{ $user->rejection_reason }}</div>
            </div>
        @endif

    @elseif ($user->account_status === 'suspended')
        <h1>Account access suspended</h1>
        <p>Please contact the barangay office regarding your account.</p>

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
        Refresh this page or sign in again to check your account status.
    </p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Sign out</button>
    </form>
</main>
</body>
</html>