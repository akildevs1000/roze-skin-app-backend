<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckConfig extends Command
{
    // Command signature and description
    protected $signature = 'config:check';
    protected $description = 'Check Laravel configuration values';

    public function handle()
    {
        $defaultMailer = config('mail.default');
        $this->info("Default Mailer: $defaultMailer");

        $mailers = config('mail.mailers');

        if (!isset($mailers[$defaultMailer])) {
            $this->error("Mailer configuration for '$defaultMailer' not found!");
            return 1;
        }

        $defaultMailerConfig = $mailers[$defaultMailer];

        // Prepare data for mailer config table
        $mailerRows = [];
        foreach ($defaultMailerConfig as $key => $value) {
            // Mask password
            if (stripos($key, 'password') !== false && $value) {
                $value = '********';
            }
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $mailerRows[] = ['Key' => $key, 'Value' => $value ?? 'null'];
        }

        $this->table(['Key', 'Value'], $mailerRows);

        // Prepare data for global from address
        $from = config('mail.from', []);
        $fromRows = [
            ['Key' => 'address', 'Value' => $from['address'] ?? 'null'],
            ['Key' => 'name', 'Value' => $from['name'] ?? 'null'],
        ];

        $this->info("Global 'from' address:");
        $this->table(['Key', 'Value'], $fromRows);

        return 0;
    }
}
