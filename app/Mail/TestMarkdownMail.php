<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMarkdownMail extends Mailable
{
    use Queueable, SerializesModels;

    public $trackingId;  // make it public to pass to the view

    // Constructor to accept tracking ID
    public function __construct($trackingId)
    {
        $this->trackingId = $trackingId;
    }

    public function build()
    {
        return $this->markdown('emails.test_markdown')
            ->subject('Shipment Status Update')
            ->with(['trackingId' => $this->trackingId]); // pass to view explicitly (optional, since public)
    }
}
