<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th scope="col">Select</th>
                <th scope="col">Resident</th>
                <th scope="col">Birth date</th>
                <th scope="col">Sitio</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($residents as $resident)
                <tr>
                    <td>
                        <input
                            id="resident_{{ $resident->id }}"
                            type="radio"
                            name="resident_id"
                            value="{{ $resident->id }}"
                            @checked((string) old('resident_id') === (string) $resident->id)
                            style="width: auto;"
                            required
                        >
                    </td>

                    <td>
                        <label for="resident_{{ $resident->id }}">
                            {{ $resident->last_name }},
                            {{ $resident->first_name }}
                            {{ $resident->middle_name }}
                        </label>

                        <div class="reference">
                            {{ $resident->resident_number }}
                        </div>
                    </td>

                    <td>{{ $resident->birth_date->format('M d, Y') }}</td>
                    <td>{{ $resident->sitio }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty">
                        No matching active residents without a household.
                        Try another search.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('households._pagination', [
    'records' => $residents,
    'paginationLabel' => 'Available residents',
])