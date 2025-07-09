<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TestMarkdownMail extends Mailable implements ShouldQueue

{
    use Queueable, SerializesModels;

    public $trackingId;  // make it public to pass to the view
    public $full_name;  // make it public to pass to the view

    // Constructor to accept tracking ID
    public function __construct($trackingId, $full_name)
    {
        $this->trackingId = $trackingId;
        $this->full_name = $full_name;
    }

    public function build()
    {
        return $this->markdown('emails.test_markdown')
            ->subject('Shipment Status Update')
            ->with(['full_name' => $this->full_name, 'trackingId' => $this->trackingId]); // pass to view explicitly (optional, since public)
    }
}
