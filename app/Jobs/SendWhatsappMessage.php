<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Models\WhatsappClient;
use Illuminate\Support\Facades\Log;

class SendWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $recipient;
    protected $message;

    public function __construct($recipient, $message)
    {
        $this->recipient = $recipient;
        $this->message = $message;
    }

    public function handle()
    {
        $accounts = WhatsappClient::value("accounts");

        if (!$accounts || !is_array($accounts) || empty($accounts[0]['clientId'])) {
            Log::info("No Whatsapp Client found.");
            return;
        }

        $clientId = $accounts[0]['clientId'];

        $payload = [
            'clientId' => $clientId,
            'recipient' => $this->recipient,
            'text' => $this->message,
        ];

        $url = 'https://wa.mytime2cloud.com/send-message';

        $response = Http::withoutVerifying()->post($url, $payload);

        if ($response->successful()) {
            Log::info("WhatsApp message sent:", $payload);
        } else {
            Log::error("Failed to send WhatsApp message to {$this->recipient}");
        }
    }
}
