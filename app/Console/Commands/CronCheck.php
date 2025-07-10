<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CronCheck extends Command
{
    protected $signature = 'cron:check';
    protected $description = 'Check if the Laravel cron is working';

    public function handle()
    {
        Log::channel('croncheck')->info('Cron is working at ' . now());
        $this->info('Logged at ' . now());
    }
}
