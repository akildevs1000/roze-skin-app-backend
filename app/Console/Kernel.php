<?php

namespace App\Console;

use App\Mail\ReportNotificationMail;
use App\Models\Company;
use App\Models\ReportNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Mail;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule
            ->command('track:shipment')
            ->hourly()
            ->emailOutputOnFailure(env("ADMIN_MAIL_RECEIVERS"));
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
