@extends('layouts.app')

@section('title', 'Dashboard')
@section('description', 'View current records and access the services available to your account.')

@section('content')
    <section class="card">
        <h2>Welcome, {{ auth()->user()->name }}</h2>

        <p>{{ $dashboardDate }} · Philippine time</p>

        <p class="muted">
            Select a service below or use the navigation menu.
        </p>
    </section>

    @if (count($summaryCards) > 0)
        <section class="card" aria-labelledby="summary-heading">
            <h2 id="summary-heading">Work summary</h2>

            <p class="muted">
                Counts reflect saved records when this page is loaded.
                Select a card to view the matching records.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 16px;">
                @foreach ($summaryCards as $card)
                    <a
                        href="{{ $card['url'] }}"
                        style="display: block; padding: 20px; border: 1px solid #d4e2da; border-radius: 12px; background: #f5faf7; color: #183b31; text-decoration: none;"
                    >
                        <span style="display: block; font-weight: bold;">
                            {{ $card['label'] }}
                        </span>

                        <strong style="display: block; margin: 12px 0; font-size: 36px; line-height: 1.2;">
                            {{ number_format($card['count']) }}
                        </strong>

                        <span style="display: block; line-height: 1.5;">
                            {{ $card['description'] }}
                        </span>

                        <span style="display: block; margin-top: 16px; text-decoration: underline;">
                            View records →
                        </span>
                    </a>
                @endforeach
            </div>

            @can('manage-residents')
                <p class="muted" style="margin-top: 20px;">
                    Active residents exclude archived records and recorded deaths.
                    These are registry counts, not a separately verified census total.
                </p>
            @endcan

            @can('process-document-requests')
                <p class="muted">
                    Document counts follow each request's saved status.
                    They include requests whose processing is currently blocked
                    by a resident status change.
                </p>
            @endcan
        </section>
    @endif

    @can('manage-residents')
        <section class="card">
            <h2>Resident Registry</h2>

            <p>
                Search resident records, register residents,
                and maintain their information and recorded status.
            </p>

            <div class="row-actions">
                <a class="button" href="{{ route('residents.index') }}">
                    Open resident registry
                </a>

                <a
                    class="button secondary"
                    href="{{ route('residents.index', ['status' => 'deceased']) }}"
                >
                    View deceased records
                </a>

                @can('access-admin')
                    <a
                        class="button secondary"
                        href="{{ route('residents.deleted') }}"
                    >
                        View archived records
                    </a>
                @endcan
            </div>
        </section>

        <section class="card">
            <h2>Household Registry</h2>

            <p>
                Organize residents into households, review membership,
                and maintain household head assignments.
            </p>

            <a class="button" href="{{ route('households.index') }}">
                Open household registry
            </a>
        </section>
    @endcan

    @can('verify-residents')
        <section class="card">
            <h2>Account Verification</h2>

            <p>
                Review resident applications and verify their
                membership in Barangay Masipit.
            </p>

            <div class="row-actions">
                <a class="button" href="{{ route('verification.index') }}">
                    View pending applications
                </a>

                <a
                    class="button secondary"
                    href="{{ route('verification.history') }}"
                >
                    Verification history
                </a>
            </div>
        </section>
    @endcan

    @can('process-document-requests')
        <section class="card">
            <h2>Document processing</h2>

            <p>
                Review online and walk-in requests, prepare certificates,
                and record document releases.
            </p>

            <div class="row-actions">
                <a
                    class="button"
                    href="{{ route('staff.documents.index') }}"
                >
                    Open document requests
                </a>

                <a
                    class="button secondary"
                    href="{{ route('staff.documents.walk-in.create') }}"
                >
                    + Walk-in request
                </a>
            </div>
        </section>
    @endcan

    @can('request-documents')
        <section class="card">
            <h2>Resident services</h2>

            <p>
                Request a Barangay Clearance, Certificate of Indigency,
                or Certificate of Residency. Monitor your request
                status and pickup instructions here.
            </p>

            <div class="row-actions">
                <a class="button" href="{{ route('documents.create') }}">
                    Request a document
                </a>

                <a
                    class="button secondary"
                    href="{{ route('documents.index') }}"
                >
                    View my requests
                </a>
            </div>
        </section>
    @endcan

    @can('access-admin')
        <section class="card">
            <h2>Administration</h2>

            <p>Access the administration workspace.</p>

            <a
                class="button secondary"
                href="{{ route('admin.dashboard') }}"
            >
                Open administration
            </a>
        </section>
    @endcan

    @can('access-health-records')
    <section class="card">
        <h2>Health Records</h2>

        <p>
            Find registered patients and access their resident-linked profiles.
        </p>

        <a class="button" href="{{ route('health.patients.index') }}">
            Open patient registry
        </a>
    </section>
@endcan

@can('manage-residents')
    <section class="card">
        <h2>Resident Census Summary</h2>

        <p>
            View resident counts by sitio, age group, and recorded sex,
            together with household and registry status totals.
        </p>

        <a class="button"
           href="{{ route('census.index') }}">
            Open census summary
        </a>
    </section>
@endcan
<section class="card">
    <h2>SMART MASIPIT Assistant</h2>

    <p>
        Ask how to use account services and document requests.
    </p>

    <a class="button" href="{{ route('chatbot.index') }}">
        Open assistant
    </a>
</section>
@endsection