<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\DailyLogReport;

class SendLogReport extends Command
{
    protected $signature = 'logs:email-report';
    protected $description = 'Email all logs from storage/logs directory';

    public function handle()
    {
        $logPath = storage_path('logs');
        $files = glob($logPath . '/*.log');

        $logSummaries = [];

        foreach ($files as $file) {
            $logSummaries[] = [
                'filename' => basename($file),
                'content' => file_get_contents($file),
                'path' => $file,
            ];
        }

        if (empty($logSummaries)) {
            $this->info('No log files found.');
            return;
        }

        Mail::to("francisgill1000@gmail.com")
            ->queue(new DailyLogReport($logSummaries));

        $this->info('✅ Daily log report sent.');
    }
}
