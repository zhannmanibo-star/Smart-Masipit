@extends('layouts.app')

@section('title', 'Request a Document')
@section('description', 'Submit a document request using your verified resident information.')

@section('actions')
    <a class="button secondary" href="{{ route('documents.index') }}">
        My requests
    </a>
@endsection

@section('content')
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
                        <th scope="row">Address details</th>
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
            If any details are incorrect, contact the barangay office
            for correction before submitting your request.
        </p>
    </section>

    <section class="card">
        <h2>Request details</h2>

        <form method="POST" action="{{ route('documents.store') }}">
            @csrf

            <input
                type="hidden"
                name="submission_token"
                value="{{ old('submission_token', $submissionToken) }}"
            >

            <div style="margin-bottom: 20px;">
                <label for="document_type">Document type *</label>

                <select id="document_type" name="document_type" required>
                    <option value="">Select a document</option>

                    @foreach ($types as $value => $label)
                        <option
                            value="{{ $value }}"
                            @selected(old('document_type') === $value)
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="purpose">Purpose of request *</label>

                <textarea
                    id="purpose"
                    name="purpose"
                    rows="4"
                    minlength="5"
                    maxlength="1000"
                    required
                    style="width: 100%; padding: 12px; border: 1px solid #bac8c1; border-radius: 7px; font: inherit;"
                    placeholder="Explain where or why you need this document."
                >{{ old('purpose') }}</textarea>
            </div>

            <label style="font-weight: 400; margin-bottom: 20px;">
                <input
                    type="checkbox"
                    name="details_confirmed"
                    value="1"
                    required
                >
                I confirm that the resident information above is correct
                and that I am requesting this document for myself.
            </label>

            <p class="muted">
                Submission is subject to barangay review and approval.
                It does not mean that a document has already been issued.
            </p>

            <button type="submit">Submit request</button>
        </form>
    </section>
@endsection