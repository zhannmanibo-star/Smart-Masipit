<?php

namespace App\Actions\Fortify;

use App\Models\ResidentApplication;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        $key = 'resident-registration:' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many registration attempts. '
                    . 'Please wait a minute before trying again.',
            ]);
        }

        RateLimiter::hit($key, 60);

        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        $data = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],

            'birth_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . now('Asia/Manila')->toDateString(),
            ],

            'sex' => ['required', 'in:Male,Female'],
            'sitio' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'bail',
                'required',
                'string',
                Password::min(12),
                'confirmed',
                function ($attribute, $value, $fail) {
                    if (strlen($value) > 72) {
                        $fail('Please use a shorter password. '
                            . 'The maximum is 72 bytes.');
                    }
                },
            ],

            'declaration' => ['accepted'],
        ])->validate();

        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = Str::squish($data[$field]);
            }
        }

        $name = implode(' ', array_filter([
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
        ], fn ($value) => $value !== null && $value !== ''));

        Validator::make(
            ['name' => $name],
            ['name' => ['required', 'string', 'max:255']]
        )->validate();

        return DB::transaction(function () use ($data, $name) {
            $user = new User();
            $user->name = $name;
            $user->email = $data['email'];
            $user->password = Hash::make($data['password']);

            // These values are controlled by the server.
            $user->role = 'resident';
            $user->account_status = 'pending';
            $user->resident_id = null;
            $user->verified_by = null;
            $user->verified_at = null;
            $user->save();

            $application = new ResidentApplication(
                Arr::only($data, [
                    'first_name',
                    'middle_name',
                    'last_name',
                    'birth_date',
                    'sex',
                    'sitio',
                    'address',
                    'contact_number',
                ])
            );

            $application->user_id = $user->id;
            $application->save();

            return $user;
        });
    }
}