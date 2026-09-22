<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Issues (and revokes) the Sanctum token used by the ChatGPT integration.
 *
 * The token belongs to a dedicated, non-master integration user with a random
 * password, so it is not tied to a person's login and revoking it never locks
 * a human out.
 */
class ChatGptToken extends Command
{
    protected $signature = 'chatgpt:token
                            {name=ChatGPT Read Only : Label shown in the token list}
                            {--pii : Also grant read-pii, which unmasks customer phone/email/address}
                            {--list : List the integration tokens and exit}
                            {--revoke= : Revoke a token by its id}';

    protected $description = 'Issue or revoke the read-only Sanctum token for the ChatGPT integration';

    private const INTEGRATION_EMAIL = 'chatgpt-integration@rozeskin.com';

    public function handle(): int
    {
        $user = $this->integrationUser();

        if ($this->option('list')) {
            return $this->listTokens($user);
        }

        if ($id = $this->option('revoke')) {
            $deleted = $user->tokens()->where('id', $id)->delete();

            $this->info($deleted ? "Token {$id} revoked." : "No token {$id} on the integration user.");

            return self::SUCCESS;
        }

        $abilities = ['read'];

        if ($this->option('pii')) {
            $abilities[] = 'read-pii';
            $this->warn('This token will return unmasked customer phone numbers, emails and addresses.');
        }

        $token = $user->createToken($this->argument('name'), $abilities);

        $this->newLine();
        $this->info('Token created. Copy it now — it is not stored and cannot be shown again.');
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->line('Abilities: ' . implode(', ', $abilities));
        $this->line('Use as:    Authorization: Bearer <token>');
        $this->line('Revoke:    php artisan chatgpt:token --revoke=' . $token->accessToken->id);
        $this->newLine();

        return self::SUCCESS;
    }

    private function listTokens(User $user): int
    {
        $tokens = $user->tokens()->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']);

        if ($tokens->isEmpty()) {
            $this->info('No ChatGPT tokens have been issued.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'name', 'abilities', 'last used', 'created'],
            $tokens->map(fn ($t) => [
                $t->id,
                $t->name,
                implode(',', (array) $t->abilities),
                $t->last_used_at ?: 'never',
                $t->created_at,
            ])->all()
        );

        return self::SUCCESS;
    }

    private function integrationUser(): User
    {
        $user = User::where('email', self::INTEGRATION_EMAIL)->first();

        if ($user) {
            return $user;
        }

        $this->info('Creating the dedicated integration user ' . self::INTEGRATION_EMAIL);

        return User::create([
            'name'              => 'ChatGPT Integration',
            'email'             => self::INTEGRATION_EMAIL,
            // Never used to log in — the account exists only to own the token.
            'password'          => Hash::make(Str::random(48)),
            'is_master'         => false,
            'role_id'           => 0,
            'company_id'        => 0,
            'branch_id'         => 0,
            'employee_role_id'  => 0,
        ]);
    }
}
