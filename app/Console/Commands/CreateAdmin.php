<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'megamall:admin';

    protected $description = 'Create a staff account that can log in to /admin (or give an existing account admin access)';

    public function handle(): int
    {
        $email = text('Email', required: true, validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email.');

        if ($user = User::where('email', $email)->first()) {
            $user->forceFill(['is_admin' => true])->save();
            $this->info("{$user->name} can now log in to /admin.");

            return self::SUCCESS;
        }

        $name = text('Full name', required: true);
        $pass = password('Password (at least 8 characters)', required: true, validate: fn (string $value) => strlen($value) >= 8 ? null : 'Use at least 8 characters.');

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $pass]);
        $user->forceFill(['is_admin' => true])->save();

        $this->info("Admin account created. Log in at ".url('/admin')." with {$email}.");

        return self::SUCCESS;
    }
}
