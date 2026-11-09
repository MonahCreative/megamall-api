<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'megamall:admin {--email= : Skip the questions (for servers without a terminal)} {--name=}';

    protected $description = 'Create a staff account that can log in to /admin (or give an existing account admin access)';

    public function handle(): int
    {
        if ($this->option('email')) {
            return $this->withoutQuestions((string) $this->option('email'), (string) ($this->option('name') ?: 'Owner'));
        }

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

    /**
     * For hosting without SSH (run as a one-off cron job). A random password is printed once:
     * copy it from the log, log in, change it under your profile, then delete the log.
     */
    private function withoutQuestions(string $email, string $name): int
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("\"{$email}\" is not a valid email.");

            return self::FAILURE;
        }

        $password = Str::password(14, symbols: false);
        $user = User::firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;
        $user->name = $user->name ?: $name;
        $user->password = $password;
        $user->forceFill(['is_admin' => true])->save();

        $this->info(($isNew ? 'Admin account created' : 'Admin access given and password reset')." for {$email}.");
        $this->line("Temporary password: {$password}");
        $this->line('Log in at '.url('/admin').', change the password under your profile (top right), then delete this log.');

        return self::SUCCESS;
    }
}
