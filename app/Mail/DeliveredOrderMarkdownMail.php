<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeliveredOrderMarkdownMail extends Mailable implements ShouldQueue

{
    use Queueable, SerializesModels;

    public $full_name;  // make it public to pass to the view
    public $message;  // make it public to pass to the view

    // Constructor to accept tracking ID
    public function __construct($full_name, $message)
    {
        $this->full_name = $full_name;
        $this->message = $message;
    }

    public function build()
    {
        return $this->markdown('emails.delivered_markdown')
            ->subject('Order Delivered')
            ->with(['full_name' => $this->full_name, "message" => $this->message]); // pass to view explicitly (optional, since public)
    }
}
