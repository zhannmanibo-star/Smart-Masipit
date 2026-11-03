<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateHealthStaff extends Command
{
    protected $signature = 'app:create-health-staff';

    protected $description = 'Create an approved health staff account';

    public function handle(): int
    {
        $name = Str::squish((string) $this->ask('Full name'));
        $email = Str::lower(trim((string) $this->ask('Email address')));

        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
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
            'password' => [
                'required',
                'confirmed',
                'max:72',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->line('Name: ' . $name);
        $this->line('Email: ' . $email);
        $this->line('Role: health_staff');
        $this->line('Account status: approved');

        if (! $this->confirm('Create this authorized health staff account?', false)) {
            return self::SUCCESS;
        }

        $user = new User();
        $user->name = $name;
        $user->email = $email;

        // User model hashes this through its password cast.
        $user->password = $password;

        $user->role = 'health_staff';
        $user->account_status = 'approved';
        $user->save();

        $this->info('Health staff account created. User ID: ' . $user->id);

        return self::SUCCESS;
    }
}