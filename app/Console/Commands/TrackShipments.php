<?php

namespace App\Console\Commands;

use App\Http\Controllers\Controller;
use App\Jobs\WhastappSender;
use App\Mail\TestMarkdownMail;
use App\Models\Order;
use App\Models\WhatsappClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class TrackShipments extends Command
{
    protected $signature = 'track:shipments';
    protected $description = 'Track multiple shipments and log the responses';

    protected $counter = 0;

    public function handle()
    {
        $payload = [
            "UserName"   => env("FIRST_FLIGHT_USER"),
            "Password"   => env("FIRST_FLIGHT_PASS"),
            "AccountNo"  => env("FIRST_FLIGHT_ACCOUNT"),
            "Country"    => env("FIRST_FLIGHT_COUNTRY"),
        ];

        $trackingsInfo = $this->getTrackingsInfo();

        $trackingsInfoCount = count($trackingsInfo);

        if (!$trackingsInfoCount) {
            $this->info('No tracking info found');
            return;
        }

        $responses = [];

        foreach ($trackingsInfo as $trackingInfo) {
            $responses[] = $this->trackShipment($trackingInfo, $payload);
        }

        $this->info(json_encode($responses, JSON_PRETTY_PRINT));
        $this->info("✅ Tracking command completed. Check laravel.log for details. {$this->counter} tracking records processed");
        Log::info(json_encode($responses, JSON_PRETTY_PRINT));
        Log::info("✅ Tracking command completed. Check laravel.log for details. {$this->counter} tracking records processed");
    }



    private function trackShipment($trackingInfo, array $payload)
    {
        $trackingId = $trackingInfo['tracking_number'];
        $full_name = $trackingInfo['customer']["full_name"] ?? null;
        $whatsapp = $trackingInfo['customer']["whatsapp"] ?? null;
        $email = $trackingInfo['customer']["email"] ?? null;
        $payload["TrackingAWB"] = $trackingId;

        // Testing Only
        $whatsapp =  "971554501483";
        $email =  "francisgill1000@gmail.com";
        $isDispatch = false;

        try {
            $data = $this->getDataFromFirstFlightApi($payload);

            if (empty($data['AirwayBillTrackList'][0]['TrackingLogDetails'])) {
                // $this->updateStatus($trackingId, "GHOST");
                return ["⚠️ No logs found for AWB: $trackingId"];
            }

            $logs = $data['AirwayBillTrackList'][0]['TrackingLogDetails'];

            if ($this->isDelivered($logs)) {
                $deliveredLog = $this->getDeliveredLog($logs);

                $deliveredTo = $deliveredLog['DeliveredTo'] ?? '';

                $deliveredAt = $this->parseDeliveredAt(
                    $deliveredLog['ActivityDate'] ?? '',
                    $deliveredLog['ActivityTime'] ?? ''
                );

                return $this->deliveredNotification($whatsapp, $email, $deliveredTo, $deliveredAt, $trackingId, $full_name, $isDispatch);
            } else {

                $notDeliveredLog = $this->getNotDeliveredLog($logs);

                if ($trackingInfo['delivery_status'] !== $notDeliveredLog['Status']) {
                    return $this->otherNotfication($trackingId, $whatsapp, $email, $full_name, $notDeliveredLog['Status'], $isDispatch);
                }
            }
        } catch (\Exception $e) {
            return ["❌ Error tracking AWB $trackingId: " . $e->getMessage()];
        }
    }

    private function getDataFromFirstFlightApi(array $payload): array
    {
        return Http::withOptions(['verify' => false])
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('https://ontrack.firstflightme.com/FFCService.svc/Tracking', $payload)
            ->json();
    }

    private function isDelivered(array $logs): bool
    {
        return in_array("POD", array_column($logs, "Status"));
    }

    private function getDeliveredLog(array $logs): ?array
    {
        return collect($logs)->firstWhere('Status', 'POD');
    }

    private function getNotDeliveredLog(array $logs): ?array
    {
        return collect($logs)->first(function ($log) {
            return $log['Status'] !== 'POD';
        });
    }

    private function parseDeliveredAt(string $date, string $time): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', "$date $time");
        } catch (\Exception $e) {
            return now(); // fallback
        }
    }

    public function deliveredNotification($whatsapp, $email, $deliveredTo, $deliveredAt, $trackingId, $full_name, $isDispatch = true)
    {
        $formattedDate = date('F j, Y \a\t h:i A', strtotime($deliveredAt));

        $message = "Dear $full_name,\n\nYour shipment has been successfully delivered to *$deliveredTo* on *$formattedDate*.\n\nThank you for choosing our service.";

        $responses = [];

        $normalizePhoneNumber = (new Controller)->normalizePhoneNumber($whatsapp);

        if ($normalizePhoneNumber) {
            $whatsappPayload = [
                'recipient' => $normalizePhoneNumber,
                'text' => $message,
                'clientId' => $this->getClient(),
            ];

            if ($trackingId && $isDispatch) {
                WhastappSender::dispatch($whatsappPayload);
            }


            $responses[] = ["whatsapp" => $whatsappPayload];
        }

        if ($email) {
            $emailPayload = [
                'recipient' => $email,
                'text' => $message,
                'subject' => "Shipment Status Update",
            ];

            if ($trackingId && $isDispatch) {
                Mail::to($email)->queue(new TestMarkdownMail($trackingId, $full_name));
            }
            $responses[] = ["email" => $emailPayload];
        }

        Order::where('tracking_number', $trackingId)->update([
            'delivery_status' => "POD",
            'delivered_to'    => $deliveredTo,
            'delivered_at'    => $deliveredAt ?? now(),
        ]);

        $this->counter++;

        return $responses;
    }

    public function otherNotfication($trackingId, $whatsapp, $email, $full_name, $status, $isDispatch = true)
    {
        $responses = [];

        $normalizePhoneNumber = (new Controller)->normalizePhoneNumber($whatsapp);

        if ($normalizePhoneNumber) {
            $whatsappPayload = [
                'recipient' => $normalizePhoneNumber,
                'text' => $this->prepareMessage($trackingId, $full_name),
                'clientId' => $this->getClient(),
            ];

            if ($trackingId && $isDispatch) {
                WhastappSender::dispatch($whatsappPayload);
            }


            $responses[] = ["whatsapp" => $whatsappPayload];
        }

        if ($email) {
            $emailPayload = [
                'recipient' => $email,
                'text' => $this->prepareMessage($trackingId, $full_name),
                'subject' => "Shipment Status Update",
            ];

            if ($trackingId && $isDispatch) {
                Mail::to($email)->queue(new TestMarkdownMail($trackingId, $full_name));
            }
            $responses[] = ["email" => $emailPayload];
        }

        Order::where('tracking_number', $trackingId)->update(['delivery_status' => $status]);

        $this->counter++;

        return $responses;
    }


    function getClient()
    {
        $clientId = WhatsappClient::value("accounts")[0]["clientId"] ?? "test";
        return $clientId;
    }

    function prepareMessage($trackingId, $full_name)
    {
        $trackingUrl = "https://rozeskin.com/tracking/?tracking_id=$trackingId";

        $message = "Dear $full_name,\n\n";
        $message .= "The status of your shipment has been updated.\n\n";
        $message .= "You can track your order using the link below:\n";
        $message .= "$trackingUrl\n\n";
        $message .= "Thank you for shopping with Roze Skincare!";

        return $message;
    }

    private function getTrackingsInfo(): array
    {
        return Order::where("tracking_number", ">", 0)
            ->whereNotNull("tracking_number")
            ->where("tracking_number", 5100308838) // FOR TESTING ONLY
            // ->where('delivery_status', '!=', 'POD')
            // ->where('delivery_status', '!=', 'GHOST')
            ->with(["customer" => function ($query) {
                $query->select("id", "first_name", "last_name", "email", "phone", "whatsapp");
                $query->withOut("shipping_address", "billing_address");
            }])
            ->get(["id", "customer_id", "tracking_number", "delivery_status"])
            ->toArray();
    }
}
