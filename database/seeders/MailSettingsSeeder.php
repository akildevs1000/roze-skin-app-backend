<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MailSetting;

class MailSettingsSeeder extends Seeder
{
    public function run()
    {

        $data = [
            'mailer' => env("MAIL_MAILER", "smtp"),
            'host' => env("MAIL_HOST", "sandbox.smtp.mailtrap.io"),
            'port' => env("MAIL_PORT", "5555"),
            'username' => env("MAIL_USERNAME"),
            'password' => env("MAIL_PASSWORD"),
            'encryption' => env("MAIL_ENCRYPTION", "tls"),
            'from_address' => env("MAIL_FROM_ADDRESS", "akildevs1000@gmail.com"),
            'from_name' => env("MAIL_FROM_NAME", config('app.name')),
        ];

        // Update existing record or create new one
        MailSetting::updateOrCreate(
            ['id' => 1],  // Assuming you want only one record with id=1
            $data
        );
    }
}
