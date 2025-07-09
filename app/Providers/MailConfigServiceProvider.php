<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Models\MailSetting;

class MailConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (app()->environment('local')) {
            // Use Mailtrap for local
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => 'sandbox.smtp.mailtrap.io',
                'mail.mailers.smtp.port' => 2525,
                'mail.mailers.smtp.username' => '1e553e26b5d8f1',
                'mail.mailers.smtp.password' => '65fe1478f5fd62',
                'mail.mailers.smtp.encryption' => 'ssl',
                'mail.from.address' => 'akildevs1000@gmail.com',
                'mail.from.name' => config('app.name'),
            ]);
            return;
        }

        // For non-local environments
        if (Schema::hasTable('mail_settings')) {
            $settings = \App\Models\MailSetting::first();

            if ($settings) {
                config([
                    'mail.default' => $settings->mailer ?? 'smtp',
                    'mail.mailers.smtp.host' => $settings->host ?? 'smtp.example.com',
                    'mail.mailers.smtp.port' => $settings->port ?? 587,
                    'mail.mailers.smtp.username' => $settings->username ?? '',
                    'mail.mailers.smtp.password' => $settings->password ?? '',
                    'mail.mailers.smtp.encryption' => $settings->encryption ?? 'tls',
                    'mail.from.address' => $settings->from_address ?? 'noreply@example.com',
                    'mail.from.name' => $settings->from_name ?? config('app.name'),
                ]);
            } elseif (env('IS_MAIL')) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => 'sandbox.smtp.mailtrap.io',
                    'mail.mailers.smtp.port' => 2525,
                    'mail.mailers.smtp.username' => '1e553e26b5d8f1',
                    'mail.mailers.smtp.password' => '65fe1478f5fd62',
                    'mail.mailers.smtp.encryption' => 'ssl',
                    'mail.from.address' => 'akildevs1000@gmail.com',
                    'mail.from.name' => config('app.name'),
                ]);
            }
        }
    }
}
