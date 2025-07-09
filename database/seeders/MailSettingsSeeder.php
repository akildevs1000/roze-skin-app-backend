<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MailSetting;

class MailSettingsSeeder extends Seeder
{
    public function run()
    {
        $data = [
            'mailer' => 'smtp',
            'host' => 'sandbox.smtp.mailtrap.io',
            'port' => 2525,
            'username' => '1e553e26b5d8f1',
            'password' => '65fe1478f5fd62',
            'encryption' => 'ssl',
            'from_address' => 'akildevs1000@gmail.com',
            'from_name' => config('app.name'),
        ];

        // Update existing record or create new one
        MailSetting::updateOrCreate(
            ['id' => 1],  // Assuming you want only one record with id=1
            $data
        );
    }
}
