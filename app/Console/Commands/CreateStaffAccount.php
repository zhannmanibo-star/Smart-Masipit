<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateStaffAccount extends Command
{
    protected $signature = 'smartmasipit:create-staff';

    protected $description =
        'Create an approved staff account in the SMART MASIPIT MySQL database';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error(
                'Stopped: the default connection must be mysql.'
            );

            return self::FAILURE;
        }

        $connection = DB::connection('mysql');

        $database = $connection
            ->selectOne('SELECT DATABASE() AS database_name')
            ->database_name;

        if ($database !== 'smart_masipit') {
            $this->error(
                'Stopped: the connected database must be smart_masipit.'
            );

            return self::FAILURE;
        }

        $this->info('Database: ' . $database);
        $this->line(
            'Host: ' . $connection->getConfig('host')
        );

        $role = $this->choice(
            'Select staff role',
            ['admin', 'captain', 'secretary', 'health_staff']
        );

        $name = Str::squish(
            (string) $this->ask('Full name')
        );

        $email = Str::lower(
            trim((string) $this->ask('Email address'))
        );

        $password = (string) $this->secret(
            'Password: at least 12 characters, with uppercase, lowercase, number and symbol',
            false
        );

        $confirmation = (string) $this->secret(
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
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('mysql.users', 'email'),
            ],
            'role' => [
                'required',
                Rule::in([
                    'admin',
                    'captain',
                    'secretary',
                    'health_staff',
                ]),
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
                function ($attribute, $value, $fail) {
                    if (strlen($value) > 72) {
                        $fail('The password must not exceed 72 bytes.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $name],
                ['Email', $email],
                ['Role', $role],
                ['Account status', 'approved'],
                ['Database', $database],
            ]
        );

        if (! $this->confirm('Create this staff account?', false)) {
            $this->info('Cancelled. No account was created.');

            return self::SUCCESS;
        }

        try {
            $user = new User();
            $user->setConnection('mysql');

            $user->name = $name;
            $user->email = $email;
            $user->password = Hash::make($password);
            $user->role = $role;
            $user->account_status = 'approved';

            $user->save();
        } catch (QueryException $exception) {
            if (
                User::on('mysql')
                    ->where('email', $email)
                    ->exists()
            ) {
                $this->error(
                    'That email already exists. No existing account was changed.'
                );

                return self::FAILURE;
            }

            throw $exception;
        }

        $this->info('Staff account created successfully.');

        $this->table(
            ['ID', 'Name', 'Role', 'Status'],
            [[
                $user->id,
                $user->name,
                $user->role,
                $user->account_status,
            ]]
        );

        if ($role === 'captain') {
            $this->warn(
                'For certificate signing, set BARANGAY_CAPTAIN_USER_ID='
                . $user->id
                . ' in your .env, then run php artisan config:clear.'
            );
        }

        return self::SUCCESS;
    }
}