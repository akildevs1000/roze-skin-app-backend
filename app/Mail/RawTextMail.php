<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RawTextMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $text;

    public function __construct(string $text)
    {
        $this->text = $text;
    }

    public function build()
    {
        return $this->subject($this->subject ?? 'Default Subject')
            ->text('emails.raw'); // plain text blade file
    }
}
