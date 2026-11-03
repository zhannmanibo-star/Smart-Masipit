<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateBarangayOfficial extends Command
{
    protected $signature = 'app:create-official';

    protected $description =
        'Create a Secretary or Captain account for SMART MASIPIT';

    public function handle(): int
    {
        $name = Str::squish((string) $this->ask('Full name'));

        $email = Str::lower(
            trim((string) $this->ask('Email address'))
        );

        $role = $this->choice(
            'Select the official role',
            ['secretary', 'captain']
        );

        $password = $this->secret(
            'Password (at least 12 characters)',
            false
        );

        $confirmation = $this->secret(
            'Confirm password',
            false
        );

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'role' => ['required', 'in:secretary,captain'],

            'password' => [
                'bail',
                'required',
                'string',
                'min:12',
                'confirmed',
                function ($attribute, $value, $fail) {
                    if (strlen($value) > 72) {
                        $fail('Please use a shorter password. '
                            . 'The maximum is 72 bytes.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = $role;
        $user->account_status = 'approved';
        $user->save();

        $this->info(
            ucfirst($role) . ' account created successfully.'
        );

        $this->line('Sign in using the email and password you entered.');

        return self::SUCCESS;
    }
}