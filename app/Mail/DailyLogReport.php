<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyLogReport extends Mailable implements ShouldQueue
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

        // Only the files the command marked small enough. Attaching everything
        // built a message far too large for the mail server to accept, and the
        // rejected job - attachments included - was then stored in the
        // database. See the note in SendLogReport.
        foreach ($this->logSummaries as $log) {
            if (empty($log['attached']) || empty($log['path'])) {
                continue;
            }

            if (file_exists($log['path'])) {
                $email->attach($log['path']);
            }
        }

        return $email;
    }
}
