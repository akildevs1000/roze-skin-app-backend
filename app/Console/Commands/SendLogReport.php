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

    /** Ceiling for the message as a whole; mail servers reject large ones. */
    private const MAX_TOTAL_BYTES = 5242880; // 5 MB

    /** A daily report covers the day. The directory holds months of files. */
    private const WINDOW_HOURS = 24;

    public function handle()
    {
        $cutoff = time() - (self::WINDOW_HOURS * 3600);

        // Only what changed today. Reporting on all 310 files in the folder
        // meant 25 MB of attachments on a single message, which is beyond what
        // the mail server accepts - the report failed for that reason alone.
        $files = array_values(array_filter(
            glob(storage_path('logs') . '/*.log') ?: [],
            fn ($f) => filemtime($f) >= $cutoff
        ));

        if (! $files) {
            $this->info('No log files written in the last ' . self::WINDOW_HOURS . ' hours.');

            return;
        }

        $logSummaries = [];
        $attach       = [];
        $running      = 0;

        foreach ($files as $file) {
            $size = filesize($file) ?: 0;

            // Deliberately no 'content' key. This used to carry
            // file_get_contents() for every log - 156 MB of text that the
            // email template never referenced. Because the mailable is queued,
            // all of it was serialised into the job, and every time the send
            // failed the whole lot was written to failed_jobs. That table had
            // reached 24 GB by 16 Oct 2026 and nearly filled the disk.
            $fits = $size <= self::MAX_ATTACH_BYTES
                && ($running + $size) <= self::MAX_TOTAL_BYTES;

            $logSummaries[] = [
                'filename' => basename($file),
                'size'     => $size,
                'attached' => $fits,
                'path'     => $file,
            ];

            if ($fits) {
                $attach[]  = $file;
                $running  += $size;
            }
        }

        SendLogEmailAndDelete::dispatch($logSummaries, 'francisgill1000@gmail.com', $attach);

        $this->info(sprintf(
            'Log report dispatched: %d file(s) from the last %dh, %d attached (%.2f MB), %d skipped.',
            count($logSummaries),
            self::WINDOW_HOURS,
            count($attach),
            $running / 1048576,
            count($logSummaries) - count($attach)
        ));
    }
}
