<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestMarkdownMail;

class SendMarkdownEmailTest extends Command
{
    protected $signature = 'email:test-markdown {email}';
    protected $description = 'Send a test email with Markdown content';

    public function handle()
    {
        $email = $this->argument('email');

        Mail::to($email)->send(new TestMarkdownMail());

        $this->info("Markdown test email sent to {$email}");
    }
}
