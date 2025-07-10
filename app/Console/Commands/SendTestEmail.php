<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'email:tester {email?} {subject?}';
    protected $description = 'Send test email using Gmail SMTP (optional recipient)';

    public function handle()
    {
        // Default values
        $to = $this->argument('email') ?? 'francisgill1000@gmail.com';
        $subject = $this->argument('subject') ?? 'Test Email from Live via Gmail';
        $text = "✅ If you're seeing this, SMTP from Live is working!";

        // Send raw email
        Mail::raw($text, function ($message) use ($to, $subject) {
            $message->to($to)
                    ->subject($subject);
        });

        $this->info("✅ Email has been sent to {$to} with subject: \"{$subject}\"");
        return 0;
    }
}
