<?php

namespace App\Console\Commands;

use App\Http\Controllers\ReportGenerateController;
use App\Jobs\GenerateReportForCompany;
use App\Models\Company;
use App\Models\EmailNotifications;
use Illuminate\Console\Command;

class GenerateReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:generate_reports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate reports for each company and queue the process';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = date('Y-m-d', strtotime('yesterday'));

        $data = EmailNotifications::where("status", 1)->get(["company_id", "email", "whatsapp_number"]);

        foreach ($data as $value) {
            echo (new ReportGenerateController)->processData($value->company_id, $date) . " " . $value->company_id . "\n";
            // GenerateReportForCompany::dispatch($value->company_id, $date);
        }
    }
}
