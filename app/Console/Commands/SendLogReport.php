<?php

namespace App\Console\Commands;

use App\Jobs\SendLogEmailAndDelete;
use Illuminate\Console\Command;

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

        // Dispatch queued job
        SendLogEmailAndDelete::dispatch($logSummaries, "francisgill1000@gmail.com", $files);

        $this->info('✅ Log email job dispatched. Logs will be deleted after successful email.');
    }
}
