<?php

namespace App\Http\Controllers;

use App\Models\WhatsappClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsappClientController extends Controller
{
    public function show()
    {
        $clients = WhatsappClient::first();
        return response()->json($clients);
    }

    public function store(Request $request)
    {
        $whatsappClient = WhatsappClient::first();

        if ($whatsappClient) {
            $whatsappClient->update(['accounts' => $request->accounts]);
        } else {
            $whatsappClient = WhatsappClient::create(['company_id' => $request->company_id, 'accounts' => $request->accounts]);
        }

        return response()->json($whatsappClient, 200);
    }

    public function send($recipient, $messsage)
    {

        $accounts = WhatsappClient::value("accounts");

        if (!$accounts || !is_array($accounts) || empty($accounts[0]['clientId'])) {
            $this->info("No Whatsapp Client found.");
            return;
        }

        $clientId = $accounts[0]['clientId'];

        $payload = [
            'clientId' => $clientId,
            'recipient' => $recipient,
            'text' => $messsage,
        ];

        $url = 'https://wa.mytime2cloud.com/send-message';

        $response = Http::withoutVerifying()->post($url, $payload);

        if ($response->successful()) {
            return json_encode($payload, JSON_PRETTY_PRINT);
        } else {
            return ("\nMessage cannot $recipient.");
        }
    }
}
