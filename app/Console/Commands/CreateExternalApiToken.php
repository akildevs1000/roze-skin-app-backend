<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateExternalApiToken extends Command
{
    protected $signature = 'token:external
                            {email : Email for the external API user}
                            {--name=external-app : Token name}';

    protected $description = 'Create (or reuse) an API user and issue a Sanctum token for an external application';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name  = $this->option('name');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => Hash::make(Str::random(40)),
            ]
        );

        $token = $user->createToken($name)->plainTextToken;

        $this->info('User ID: ' . $user->id);
        $this->info('Token name: ' . $name);
        $this->line('');
        $this->line('Bearer token (store securely, shown only once):');
        $this->line($token);

        return self::SUCCESS;
    }
}
