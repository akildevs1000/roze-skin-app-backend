<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    // "email" is now optional with the `?`
    protected $signature = 'email:tester {email?}';
    protected $description = 'Send test email using Gmail SMTP (optional recipient)';

    public function handle()
    {
        // Use provided email or default to francisgill1000@gmail.com
        $to = $this->argument('email') ?? 'francisgill1000@gmail.com';

        try {
            Mail::raw("If you're seeing this, SMTP from Live is working!", function ($message) use ($to) {
                $message->to($to)
                    ->subject('Test Email from Live via Gmail');
            });

            $this->info("✅ Email sent to {$to} successfully!");
        } catch (\Exception $e) {
            $this->error("❌ Failed to send email: " . $e->getMessage());
        }
    }
}
