<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyLogReport extends Mailable
{
    use Queueable, SerializesModels;

    public $logSummaries;

    public function __construct($logSummaries)
    {
        $this->logSummaries = $logSummaries;
    }

    public function build()
    {
        $email = $this->subject('📝 Daily Log Report - ' . now()->format('Y-m-d'))
            ->view('emails.daily-log-report')
            ->with(['logSummaries' => $this->logSummaries]);

        // Optional: attach log files
        foreach ($this->logSummaries as $log) {
            if (file_exists($log['path'])) {
                $email->attach($log['path']);
            }
        }

        return $email;
    }
}
