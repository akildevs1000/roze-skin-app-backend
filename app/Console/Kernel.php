<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // $schedule
        //     ->command('track:shipments-test')
        //     ->everyMinute();

        $schedule
            ->command('track:shipments')
            ->hourlyAt(15);

        // $schedule
        //     ->command('cron:check')
        //     ->hourly();

        $schedule->command('logs:email-report')->dailyAt('08:00');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
