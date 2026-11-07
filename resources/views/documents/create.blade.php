@extends('layouts.app')

@section('title', 'Request a Document')
@section('description', 'Submit a document request using your verified resident information.')

@section('actions')
    <a class="button secondary" href="{{ route('documents.index') }}">My requests</a>
@endsection

@section('content')
    @if ($errors->any())
        <section class="card" role="alert">
            <h2>Please review the following</h2>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card">
        <h2>Your verified information</h2>

        <div class="table-wrap">
            <table>
                <tbody>
                    <tr>
                        <th scope="row">Resident</th>
                        <td>
                            {{ $resident->first_name }}
                            {{ $resident->middle_name }}
                            {{ $resident->last_name }}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Birth date</th>
                        <td>{{ $resident->birth_date->format('M d, Y') }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Sitio</th>
                        <td>{{ $resident->sitio }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Address</th>
                        <td>{{ $resident->address ?: 'Not recorded' }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Barangay</th>
                        <td>Masipit, Calapan City, Oriental Mindoro</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="muted">
            Contact the barangay office for correction if any identity
            or address details are incorrect before submitting.
        </p>
    </section>

    <section class="card">
        <h2>Request details</h2>

        <form method="POST" action="{{ route('documents.store') }}">
            @csrf
            @include('documents.partials.request-fields', ['walkIn' => false])
        </form>
    </section>
@endsection