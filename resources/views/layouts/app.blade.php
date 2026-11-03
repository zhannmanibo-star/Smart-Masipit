<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') | SMART MASIPIT</title>

    <style>
        :root {
            --green: #16634b;
            --green-dark: #103f33;
            --background: #f4f6f8;
            --border: #dfe5e9;
            --text: #20312c;
            --muted: #60716b;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 15px;
            line-height: 1.5;
        }

        a { color: var(--green); }

        button, input, select { font: inherit; }

        :focus-visible {
            outline: 3px solid #d4a843;
            outline-offset: 3px;
        }

        .skip-link {
            position: absolute;
            left: 16px;
            top: -100px;
            padding: 12px;
            background: white;
            z-index: 10;
        }

        .skip-link:focus { top: 16px; }

        .shell {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            background: var(--green-dark);
            color: white;
            padding: 30px 20px;
        }

        .brand {
            font-size: 21px;
            font-weight: 750;
            letter-spacing: .5px;
        }

        .brand-subtitle {
            color: #c4d9d0;
            font-size: 13px;
            margin-top: 6px;
        }

        .nav-label {
            margin: 36px 12px 12px;
            color: #c4d9d0;
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .sidebar nav a {
            display: block;
            margin-bottom: 6px;
            padding: 12px 14px;
            color: #edf5f1;
            text-decoration: none;
            border-radius: 8px;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: #255c4c;
        }

        .sidebar nav a.active {
            box-shadow: inset 3px 0 #e3bd64;
        }

        .workspace { min-width: 0; }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 18px 32px;
            background: white;
            border-bottom: 1px solid var(--border);
        }

        .account {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .account-name { font-weight: 600; }

        .muted {
            color: var(--muted);
            font-size: 13px;
        }

        .content {
            max-width: 1400px;
            margin: auto;
            padding: 32px;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 28px;
            letter-spacing: -.6px;
        }

        h2 { margin-top: 0; font-size: 19px; }

        .page-heading p { margin: 0; color: var(--muted); }

        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .button,
        button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 10px 16px;
            border: 1px solid var(--green);
            border-radius: 7px;
            background: var(--green);
            color: white;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .button:hover, button:hover { background: var(--green-dark); }

        .button.secondary {
            background: white;
            color: var(--green);
            border-color: var(--border);
        }

        .button.secondary:hover { background: #eef5f1; }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 12px;
        }

        .search-field { flex: 1 1 280px; }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 7px;
        }

        input, select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #bac8c1;
            border-radius: 7px;
            background: white;
            color: var(--text);
        }

        input[type="checkbox"] { width: auto; }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin: 24px 0;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .alert.success {
            background: #e4f3eb;
            border: 1px solid #badcc9;
            color: #15563b;
        }

        .alert.error {
            background: #fff2f1;
            border: 1px solid #edc6c3;
            color: #922c27;
        }

        .alert ul { margin-bottom: 0; }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #f5f8f6;
            color: #51665b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        th, td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        tbody tr:hover { background: #fafcfb; }

        .reference {
            font-family: monospace;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .empty {
            padding: 48px 20px;
            text-align: center;
            color: var(--muted);
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            padding-top: 20px;
        }

        .pagination-links {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        @media (max-width: 900px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar { padding: 20px; }
            .nav-label { margin-top: 20px; }
            .sidebar nav { display: flex; flex-wrap: wrap; gap: 8px; }
            .sidebar nav a { margin: 0; }
        }

        @media (max-width: 600px) {
            .content { padding: 20px 14px; }
            .topbar { padding: 16px; flex-wrap: wrap; }
            .page-heading { align-items: start; flex-direction: column; }
            .grid { grid-template-columns: 1fr; }
            .card { padding: 18px; }
            .account { flex-wrap: wrap; }
        }

        .row-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.row-actions form {
    margin: 0;
}

button.danger {
    background: #fff5f5;
    border-color: #e4baba;
    color: #a12626;
}

button.danger:hover {
    background: #a12626;
    border-color: #a12626;
    color: white;
}

.decision-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.decision-badge.approved {
    background: #e4f3eb;
    color: #15563b;
}

.decision-badge.rejected {
    background: #fff0ee;
    color: #922c27;
}

.review-details summary {
    cursor: pointer;
    color: #16634b;
    font-weight: 600;
}

.review-note {
    min-width: 200px;
    max-width: 360px;
    margin-top: 12px;
    padding: 14px;
    border: 1px solid #dfe5e9;
    border-radius: 8px;
    background: #f7f9f8;
}

.review-note p {
    margin: 8px 0 16px;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
    </style>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>

<div class="shell">
    <aside class="sidebar">
        <div class="brand">SMART MASIPIT</div>
        <div class="brand-subtitle">
            Barangay Information System
        </div>

        <div class="nav-label">Workspace</div>

        <nav aria-label="Main navigation">
            <a href="{{ route('dashboard') }}"
               class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                Dashboard
            </a>

            @can('process-document-requests')
    <a
        href="{{ route('staff.documents.index') }}"
        class="{{ request()->routeIs('staff.documents.*') ? 'active' : '' }}"
    >
        Document Requests
    </a>
@endcan

            @can('request-documents')
    <a
        href="{{ route('documents.index') }}"
        class="{{ request()->routeIs('documents.*') ? 'active' : '' }}"
    >
        My Document Requests
    </a>
@endcan

@can('manage-residents')
    <a
        href="{{ route('households.index') }}"
        class="{{ request()->routeIs('households.*') ? 'active' : '' }}"
    >
        Household Registry
    </a>
@endcan

            @can('manage-residents')
                <a href="{{ route('residents.index') }}"
                   class="{{ request()->routeIs('residents.*') ? 'active' : '' }}">
                    Resident Registry
                </a>
            @endcan

            @can('verify-residents')
    <a
        href="{{ route('verification.index') }}"
        class="{{ request()->routeIs('verification.*') ? 'active' : '' }}"
    >
        Account Verification
    </a>
@endcan

            @can('access-admin')
                <a href="{{ route('admin.dashboard') }}"
                   class="{{ request()->routeIs('admin.*') ? 'active' : '' }}">
                    Administration
                </a>
            @endcan
        </nav>
    </aside>

    <div class="workspace">
        <header class="topbar">
            <div>
                <strong>Barangay Masipit</strong>
                <div class="muted">Calapan City, Oriental Mindoro</div>
            </div>

            <div class="account">
                <div>
                    <div class="account-name">
                        {{ auth()->user()->name }}
                    </div>
                    <div class="muted">
                        {{ ucwords(str_replace('_', ' ', auth()->user()->role)) }}
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Sign out</button>
                </form>
            </div>
        </header>

        <main id="main-content" class="content">
            <div class="page-heading">
                <div>
                    <h1>@yield('title')</h1>
                    <p>@yield('description')</p>
                </div>

                @yield('actions')
            </div>

            @if (session('success'))
                <div class="alert success" role="status">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert error" role="alert">
                    <strong>Please review the following:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>