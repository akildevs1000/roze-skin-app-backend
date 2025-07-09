<?php

namespace App\Console\Commands;

use App\Jobs\SendRawEmailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    // "email" is now optional with the `?`
    protected $signature = 'email:tester {email?} {subject?}';
    protected $description = 'Send test email using Gmail SMTP (optional recipient)';

    public function handle()
    {

        Mail::send([], [], function ($message) {
            $message->to('francisgill1000@gmail.com')
                ->subject('Test HTML Email')
                ->setBody('<h1>Hello!</h1><p>This is a test email.</p>', 'text/html');
        });

        return;

        // Use provided email or default to francisgill1000@gmail.com
        $to = $this->argument('email') ?? 'francisgill1000@gmail.com';
        $subject = $this->argument('subject') ?? 'Test Email from Live via Gmail';
        $text = "If you're seeing this, SMTP from Live is working!";
        SendRawEmailJob::dispatch($to, $subject, $text);
        $this->info("✅ Email has been queued to {$to} with subject: \"{$subject}\"");

        return 0;
    }
}
