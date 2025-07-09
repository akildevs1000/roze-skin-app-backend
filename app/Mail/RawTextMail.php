<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RawTextMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $trackingId;

    // public function __construct(string $trackingId)
    // {
    //     $this->trackingId = $trackingId;
    // }

    public function build()
    {
        
        return $this->subject('Shipment Update')
            ->view('emails.raw')
            ->with(['trackingId' => 5100308838]);
    }
}
