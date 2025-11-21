<?php

namespace App\Console\Commands;

use App\Http\Controllers\InvoiceController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestEmxLabelCommand extends Command
{
    protected $signature = 'emx:test-label {awb=1000038019168}';
    protected $description = 'Test EMX label print API using an AWB number';

    public function handle()
    {
        (new InvoiceController)->printLabel($this->argument('awb'));
        return Command::SUCCESS;
    }
}
