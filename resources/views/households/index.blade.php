@extends('layouts.app')

@section('title', 'Household Registry')
@section('description', 'Manage household records and their registered members.')

@section('actions')
    <a class="button" href="{{ route('households.create') }}">
        + Create household
    </a>
@endsection

@section('content')
    <section class="card">
        <form
            method="GET"
            action="{{ route('households.index') }}"
            class="toolbar"
            role="search"
        >
            <div class="search-field">
                <label for="search">Find a household</label>

                <input
                    id="search"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    maxlength="100"
                    placeholder="Household number, head name, or sitio"
                >
            </div>

            <button type="submit">Search</button>

            <a class="button secondary" href="{{ route('households.index') }}">
                Clear
            </a>
        </form>
    </section>

    <section class="card">
        <h2>Households ({{ $households->total() }})</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Household number</th>
                        <th scope="col">Head of household</th>
                        <th scope="col">Sitio</th>
                        <th scope="col">Active members</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($households as $household)
                        <tr>
                            <td class="reference">
                                {{ $household->household_number }}
                            </td>

                            <td>
                                {{ $household->head?->first_name }}
                                {{ $household->head?->middle_name }}
                                {{ $household->head?->last_name }}

                                @if ($household->head?->trashed())
                                    <div class="muted">Head record archived</div>
                                @endif
                            </td>

                            <td>{{ $household->sitio }}</td>

                            <td>{{ $household->active_memberships_count }}</td>

                            <td>
                                <a
                                    class="button secondary"
                                    href="{{ route('households.show', $household) }}"
                                >
                                    Open household
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">
                                No households found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="muted">
            Active member counts include the head when their resident
            record is active.
        </p>

        @include('households._pagination', [
            'records' => $households,
            'paginationLabel' => 'Household pages',
        ])
    </section>
@endsection