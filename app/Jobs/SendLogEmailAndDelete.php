<?php

namespace App\Jobs;

use App\Mail\DailyLogReport;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendLogEmailAndDelete implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $logSummaries;
    protected $emails;
    protected $files;

    public function __construct(array $logSummaries, array|string $emails, array $files)
    {
        $this->logSummaries = $logSummaries;
        $this->emails = $emails;
        $this->files = $files;
    }

    public function handle()
    {
        Mail::to($this->emails)->send(new DailyLogReport($this->logSummaries));

        // Delete log files
        // foreach ($this->files as $file) {
        //     if (file_exists($file)) {
        //         unlink($file);
        //     }
        // }
    }
}
