<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Resident Account | SMART MASIPIT</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 36px 20px;
            background: #f4f6f8;
            color: #20312c;
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
        }

        main {
            max-width: 860px;
            margin: auto;
            padding: 36px;
            background: white;
            border: 1px solid #dfe5e9;
            border-radius: 14px;
        }

        .brand { color: #16634b; font-weight: 750; letter-spacing: 1px; }
        .muted { color: #60716b; }
        h1 { line-height: 1.3; }

        .notice, .errors {
            padding: 16px 18px;
            margin: 24px 0;
            border: 1px solid #c6ddd2;
            border-radius: 8px;
            background: #eff7f2;
        }

        .errors {
            border-color: #edc6c3;
            background: #fff2f1;
            color: #922c27;
        }

        fieldset {
            border: 0;
            border-top: 1px solid #dfe5e9;
            padding: 24px 0;
            margin: 0;
            min-width: 0;
        }

        legend { font-size: 19px; font-weight: 700; }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        label { display: block; margin-bottom: 7px; font-weight: 600; }

        input, select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #bac8c1;
            border-radius: 7px;
            background: white;
            font: inherit;
        }

        .checkbox {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-weight: 400;
        }

        .checkbox input { width: auto; margin-top: 7px; }

        button {
            padding: 13px 22px;
            border: 0;
            border-radius: 7px;
            background: #16634b;
            color: white;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        button:hover { background: #103f33; }
        a { color: #16634b; }

        :focus-visible {
            outline: 3px solid #d4a843;
            outline-offset: 3px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            margin-top: 24px;
        }

        @media (max-width: 600px) {
            body { padding: 16px; }
            main { padding: 22px; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main>
    <div class="brand">SMART MASIPIT</div>
    <div class="muted">Barangay Masipit · Calapan City</div>

    <h1>Create a resident account</h1>

    <p class="muted">
        Enter your details for barangay verification.
        Fields marked * are required.
    </p>

    <div class="notice">
        Your account will remain pending until the Barangay Secretary
        or Captain reviews your information, ID, and selfie.
        Registration does not automatically confirm residency.
    </div>

    @if ($errors->any())
        <div class="errors" role="alert">
            <strong>Please review the following:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ url('/register') }}"
        enctype="multipart/form-data"
    >
        @csrf

        <fieldset>
            <legend>Personal information</legend>

            <div class="grid">
                <div>
                    <label for="first_name">First name *</label>
                    <input id="first_name" name="first_name"
                           value="{{ old('first_name') }}"
                           maxlength="100" autocomplete="given-name" required>
                </div>

                <div>
                    <label for="middle_name">Middle name</label>
                    <input id="middle_name" name="middle_name"
                           value="{{ old('middle_name') }}"
                           maxlength="100" autocomplete="additional-name">
                </div>

                <div>
                    <label for="last_name">Last name *</label>
                    <input id="last_name" name="last_name"
                           value="{{ old('last_name') }}"
                           maxlength="100" autocomplete="family-name" required>
                </div>

                <div>
                    <label for="birth_date">Birth date *</label>
                    <input id="birth_date" type="date" name="birth_date"
                           value="{{ old('birth_date') }}"
                           max="{{ now('Asia/Manila')->toDateString() }}"
                           autocomplete="bday" required>
                </div>

                <div>
                    <label for="sex">Sex *</label>
                    <select id="sex" name="sex" required>
                        <option value="">Select</option>
                        <option value="Male" @selected(old('sex') === 'Male')>
                            Male
                        </option>
                        <option value="Female" @selected(old('sex') === 'Female')>
                            Female
                        </option>
                    </select>
                </div>

                <div>
                    <label for="contact_number">Contact number</label>
                    <input id="contact_number" type="tel" name="contact_number"
                           value="{{ old('contact_number') }}"
                           maxlength="30" autocomplete="tel">
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Residence information</legend>

            <p class="muted">
                Barangay Masipit, Calapan City, Oriental Mindoro
            </p>

            <div class="grid">
                <div>
                    <label for="sitio">Sitio *</label>
                    <input id="sitio" name="sitio" value="{{ old('sitio') }}"
                           maxlength="100" required>
                </div>

                <div>
                    <label for="address">House number / Street / Purok</label>
                    <input id="address" name="address"
                           value="{{ old('address') }}"
                           maxlength="255" autocomplete="address-line1">
                </div>
            </div>
        </fieldset>

        @include('auth.partials.identity-fields')

        <fieldset>
            <legend>Login details</legend>

            <label for="email">Email address *</label>
            <input id="email" type="email" name="email"
                   value="{{ old('email') }}"
                   maxlength="255" autocomplete="username" required>

            <div class="grid" style="margin-top: 20px;">
                <div>
                    <label for="password">Password *</label>
                    <input id="password" type="password" name="password"
                           minlength="12" autocomplete="new-password"
                           aria-describedby="password-help" required>
                    <small id="password-help" class="muted">
                        Use at least 12 characters.
                    </small>
                </div>

                <div>
                    <label for="password_confirmation">Confirm password *</label>
                    <input id="password_confirmation" type="password"
                           name="password_confirmation"
                           minlength="12" autocomplete="new-password" required>
                </div>
            </div>
        </fieldset>

        <label class="checkbox">
            <input type="checkbox" name="declaration" value="1" required>
            <span>
                I confirm that the information I provided is accurate
                and understand that my residency must be verified
                before I can access resident services.
            </span>
        </label>

        <div class="actions">
            <button type="submit">Submit registration</button>
            <a href="{{ route('login') }}">Already registered? Sign in</a>
        </div>
    </form>
</main>
</body>
</html>