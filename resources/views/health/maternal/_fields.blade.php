@foreach ($sections as $section => $fields)
    <fieldset style="border: 1px solid #ccd8d2; padding: 20px; margin: 20px 0;">
        <legend style="font-weight: bold; padding: 0 8px;">
            {{ $section }}
        </legend>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px;">
            @foreach ($fields as $key => [$label, $type])
                <div>
                    <label for="{{ $key }}">
                        {{ $label }}{{ $key === 'clinic' ? ' *' : '' }}
                    </label>

                    @if ($type === 'choice')
                        <select id="{{ $key }}" name="{{ $key }}"
                                style="width: 100%;">
                            <option value="">Not recorded / unknown</option>
                            <option value="yes" @selected(old($key) === 'yes')>
                                Yes
                            </option>
                            <option value="no" @selected(old($key) === 'no')>
                                No
                            </option>
                        </select>
                    @elseif ($type === 'count')
                        <input id="{{ $key }}" name="{{ $key }}"
                               type="number" min="0" max="99" step="1"
                               value="{{ old($key) }}"
                               style="width: 100%;">
                    @elseif ($type === 'date')
                        <input id="{{ $key }}" name="{{ $key }}"
                               type="date" value="{{ old($key) }}"
                               style="width: 100%;">
                    @else
                        <textarea id="{{ $key }}" name="{{ $key }}"
                                  rows="2"
                                  maxlength="{{ $key === 'clinic' ? 100 : 250 }}"
                                  @required($key === 'clinic')
                                  style="width: 100%; padding: 10px; font: inherit; border: 1px solid #bac8c1; border-radius: 6px;">{{ old($key) }}</textarea>
                    @endif
                </div>
            @endforeach
        </div>
    </fieldset>
@endforeach