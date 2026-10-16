<?php

namespace App\Console\Commands;

use App\Jobs\SendLogEmailAndDelete;
use Illuminate\Console\Command;

class SendLogReport extends Command
{
    protected $signature = 'logs:email-report';
    protected $description = 'Email a summary of the logs in storage/logs';

    /**
     * Largest log file worth attaching. Above this the file is listed in the
     * email but not sent: a queued job carries its own arguments, so a big
     * attachment is held in the database as well as in the message.
     */
    private const MAX_ATTACH_BYTES = 1048576; // 1 MB

    public function handle()
    {
        $files = glob(storage_path('logs') . '/*.log');

        if (! $files) {
            $this->info('No log files found.');

            return;
        }

        $logSummaries = [];
        $attach       = [];

        foreach ($files as $file) {
            $size = filesize($file) ?: 0;

            // Deliberately no 'content' key. This used to carry
            // file_get_contents() for every log - 156 MB of text that the
            // email template never referenced. Because the mailable is queued,
            // all of it was serialised into the job, and every time the send
            // failed the whole lot was written to failed_jobs. That table had
            // reached 24 GB by 16 Oct 2026 and nearly filled the disk.
            $logSummaries[] = [
                'filename' => basename($file),
                'size'     => $size,
                'attached' => $size <= self::MAX_ATTACH_BYTES,
                'path'     => $file,
            ];

            if ($size <= self::MAX_ATTACH_BYTES) {
                $attach[] = $file;
            }
        }

        SendLogEmailAndDelete::dispatch($logSummaries, 'francisgill1000@gmail.com', $attach);

        $this->info(sprintf(
            'Log report dispatched: %d file(s), %d attached, %d too large.',
            count($logSummaries),
            count($attach),
            count($logSummaries) - count($attach)
        ));
    }
}
