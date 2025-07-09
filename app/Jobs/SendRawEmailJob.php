<?php

namespace App\Jobs;

use App\Mail\RawTextMail;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        try {
            Log::info("Sending raw text mail to {$this->to}");
            Mail::to($this->to)->send((new RawTextMail($this->text))->subject($this->subject));
            Log::info("✅ Email sent successfully to {$this->to}");
        } catch (\Throwable $e) {
            Log::error("❌ Failed to send email to {$this->to}");
            Log::error($e->getMessage());
        }
    }
}
