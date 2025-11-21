<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestEmxLabelCommand extends Command
{
    protected $signature = 'emx:test-label {awb=1000038016196}';
    protected $description = 'Test EMX label print API using an AWB number';

    public function handle()
    {
        $awb = $this->argument('awb');
        $apiKey = env("EMX_ORDER_CREATE_API_KEY");

        if (!$apiKey) {
            $this->error("❌ EMX API Key not found in .env");
            return Command::FAILURE;
        }

        $url = "https://local.epservices.ae/api/Label/print?awb={$awb}";

        $this->info("📄 Fetching EMX Label for AWB: $awb");
        Log::channel('order_emx')->info("Fetching EMX Label", ['awb' => $awb, 'url' => $url]);

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'x-api-key' => $apiKey,
                'Accept'    => 'application/pdf',
            ])->get($url);
        } catch (\Exception $e) {
            $this->error("❌ API call failed: " . $e->getMessage());
            Log::channel('order_emx')->error("EMX API call exception", ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        if (!$response->successful()) {
            $this->error("❌ Failed to fetch label, status: {$response->status()}");
            Log::channel('order_emx')->error("Failed to fetch EMX label", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return Command::FAILURE;
        }

        // Auto-save to Windows Downloads
        if (PHP_OS_FAMILY === "Windows") {

            // Define folder path inside Downloads
            $folderName = "EMX_AWB_NUMBERS"; // folder name
            $folderPath = getenv("USERPROFILE") . "\\Downloads\\{$folderName}";

            // Create folder if it doesn't exist
            if (!is_dir($folderPath)) {
                mkdir($folderPath, 0777, true); // true = recursive
            }

            // File path inside the folder
            $downloadsPath = $folderPath . "\\{$awb}.pdf";

            // Save PDF
            file_put_contents($downloadsPath, $response->body());

            $this->info("📥 Label auto-downloaded: $downloadsPath");
            Log::channel('order_emx')->info("Label auto-downloaded", ['path' => $downloadsPath]);
        }

        return Command::SUCCESS;
    }
}
