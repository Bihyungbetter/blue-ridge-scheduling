<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

class CreateStaffUser extends Command
{
    protected $signature = 'staff:create {name} {email}';

    protected $description = 'Create a staff login (self-registration is disabled)';

    public function handle(): int
    {
        $validator = Validator::make($this->arguments(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = password('Password', required: true, validate: fn (string $value) => strlen($value) < 12 ? 'Use at least 12 characters.' : null);

        User::forceCreate([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->components->info("Created staff user {$this->argument('email')}.");

        return self::SUCCESS;
    }
}
