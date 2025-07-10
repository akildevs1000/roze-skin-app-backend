<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Models\MailSetting;

class MailConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (Schema::hasTable('mail_settings')) {
            $settings = \App\Models\MailSetting::first();
            config([
                'mail.default' => $settings->mailer ?? 'smtp',
                'mail.mailers.smtp.host' => $settings->host ?? 'smtp.gmail.com',
                'mail.mailers.smtp.port' => $settings->port ?? 587,
                'mail.mailers.smtp.username' => $settings->username ?? '',
                'mail.mailers.smtp.password' => $settings->password ?? '',
                'mail.mailers.smtp.encryption' => $settings->encryption ?? 'tls',
                'mail.from.address' => $settings->from_address ?? 'noreply@example.com',
                'mail.from.name' => $settings->from_name ?? config('app.name'),
            ]);
        }
    }
}
