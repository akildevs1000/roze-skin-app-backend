<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TruncateTable extends Command
{
    protected $signature = 'table:truncate {table}';

    protected $description = 'Truncate the given database table';

    public function handle()
    {
        $table = $this->argument('table');

        if (!Schema::hasTable($table)) {
            $this->error("Table '{$table}' does not exist.");
            return Command::FAILURE;
        }

        try {
            DB::table($table)->truncate();
            $this->info("Table '{$table}' truncated successfully.");

            // Cache::forget('order_stats_last_month');
            // Cache::forget('invoice_stats_last_month');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to truncate table '{$table}': " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
