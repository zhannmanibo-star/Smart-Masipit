@php
    $profile = $resident ?? null;

    $fields = [
        'first_name' => ['First name', 'text', 100, true],
        'middle_name' => ['Middle name', 'text', 100, false],
        'last_name' => ['Last name', 'text', 100, true],
        'birth_date' => ['Birth date', 'date', null, true],
        'sitio' => ['Sitio', 'text', 100, true],
     'address' => ['House number', 'text', 255, true],
        'contact_number' => ['Contact number', 'tel', 30, false],
    ];
@endphp

<div class="grid">
    @foreach ($fields as $name => [$label, $type, $limit, $required])
        @php
            $value = $name === 'birth_date'
                ? $profile?->birth_date?->format('Y-m-d')
                : $profile?->{$name};
        @endphp

        <div>
            <label for="{{ $name }}">
                {{ $label }} {{ $required ? '*' : '' }}
            </label>

            <input
                id="{{ $name }}"
                name="{{ $name }}"
                type="{{ $type }}"
                value="{{ old($name, $value) }}"
                @if ($limit) maxlength="{{ $limit }}" @endif
                @if ($type === 'date')
                    max="{{ now()->toDateString() }}"
                @endif
                @required($required)
            >
        </div>
    @endforeach

    <div>
        <label for="sex">Sex *</label>
        <select id="sex" name="sex" required>
            <option value="">Select</option>

            @foreach (['Male', 'Female'] as $sex)
                <option
                    value="{{ $sex }}"
                    @selected(old('sex', $profile?->sex) === $sex)
                >
                    {{ $sex }}
                </option>
            @endforeach
        </select>
    </div>
</div>

@if ($errors->has('duplicate'))
    <div style="margin-top: 20px;">
        <label>
            <input
                type="checkbox"
                name="confirm_distinct_person"
                value="1"
                style="width: auto;"
            >
            I reviewed the matching record and verified that this
            is a different person.
        </label>
    </div>
@endif

<button type="submit">{{ $buttonText }}</button>