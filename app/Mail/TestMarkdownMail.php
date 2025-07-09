<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMarkdownMail extends Mailable
{
    use Queueable, SerializesModels;

    public function build()
    {
        return $this->markdown('emails.test_markdown')
            ->subject('Markdown Email Test');
    }
}
