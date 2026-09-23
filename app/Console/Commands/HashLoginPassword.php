<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class HashLoginPassword extends Command
{
    protected $signature = 'auth:hash-password';

    protected $description = 'Print a bcrypt hash of a login password to paste into APP_LOGIN_PASSWORD';

    public function handle(): int
    {
        $password = (string) $this->secret('Login password');

        if (strlen($password) < 8) {
            $this->error('Use at least 8 characters.');

            return self::FAILURE;
        }

        // Single quotes stop dotenv from expanding the "$" segments of the hash.
        $this->line("APP_LOGIN_PASSWORD='".Hash::make($password)."'");

        return self::SUCCESS;
    }
}
