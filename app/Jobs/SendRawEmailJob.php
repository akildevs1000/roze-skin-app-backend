<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendRawEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $to;
    public $subject;
    public $text;

    public function __construct($to, $subject, $text)
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->text = $text;
    }

    public function handle()
    {
        Mail::raw($this->text, function ($message) {
            $message->to($this->to)
                    ->subject($this->subject);
        });
    }
}
