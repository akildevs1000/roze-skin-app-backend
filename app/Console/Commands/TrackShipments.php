<?php

namespace App\Console\Commands;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmail;
use App\Jobs\WhastappSender;
use App\Mail\TestMarkdownMail;
use App\Models\Order;
use App\Models\WhatsappClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class TrackShipments extends Command
{
    protected $signature = 'track:shipments';
    protected $description = 'Track multiple shipments and log the responses';

    protected $counter = 0;

    public function handle()
    {

        // $emailPayload = [
        //     'recipient' => "francisgill1000@gmail.com",
        //     'text' => "If you're seeing this, SMTP from Live is working!",
        //     'subject' => "Test Email from Live via Gmail"
        // ];

        // SendEmail::dispatch($emailPayload);

        // $whatsappPayload = [
        //     'recipient' => "971554501483",
        //     'text' => "If you're seeing this, Whatsapp is working!", // ✅ Use the full message, not just the link
        //     'clientId' => $this->getClient(),
        // ];

        // WhastappSender::dispatch($whatsappPayload);

        // return;

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

        foreach ($trackingsInfo as $trackingInfo) {
            $this->trackShipment($trackingInfo, $payload);
        }

        $this->info("✅ Tracking command completed. Check laravel.log for details. {$this->counter} tracking records processed");
    }

    private function getTrackingsInfo(): array
    {
        return Order::where("tracking_number", ">", 0)
            ->whereNotNull("tracking_number")
            // ->where("tracking_number", 5100308838) // FOR TESTING ONLY
            ->where('delivery_status', '!=', 'POD')
            ->where('delivery_status', '!=', 'GHOST')
            ->with(["customer" => function ($query) {
                $query->select("id", "first_name", "last_name", "email", "phone", "whatsapp");
                $query->withOut("shipping_address", "billing_address");
            }])
            ->get(["id", "customer_id", "tracking_number", "delivery_status"])
            ->toArray();
    }

    private function trackShipment($trackingInfo, array $payload): void
    {
        $trackingId = $trackingInfo['tracking_number'];
        $whatsapp = $trackingInfo['customer']["whatsapp"] ?? null;
        $email = $trackingInfo['customer']["email"] ?? null;
        $payload["TrackingAWB"] = $trackingId;

        // Testing Only

        if ($trackingId == 5100308838) {
            $whatsapp =  "971554501483";
            $email =  "francisgill1000@gmail.com";
            $responses = $this->otherNotfication($trackingId, $whatsapp, $email);
            $this->info(json_encode($responses, JSON_PRETTY_PRINT));
            return;
        }

        // Testing Only End

        try {
            $data = $this->getDataFromFirstFlightApi($payload);

            if (empty($data['AirwayBillTrackList'][0]['TrackingLogDetails'])) {
                Log::warning("⚠️ No logs found for AWB: $trackingId");
                $this->updateStatus($trackingId, "GHOST");
                return;
            }

            $logs = $data['AirwayBillTrackList'][0]['TrackingLogDetails'];

            if ($this->isDelivered($logs)) {
                $deliveredLog = $this->getDeliveredLog($logs);

                $deliveredTo = $deliveredLog['DeliveredTo'] ?? '';

                $deliveredAt = $this->parseDeliveredAt(
                    $deliveredLog['ActivityDate'] ?? '',
                    $deliveredLog['ActivityTime'] ?? ''
                );

                $responses = $this->deliveredNotification($whatsapp, $email, $deliveredTo, $deliveredAt, $trackingId);
                Log::info(json_encode($responses, JSON_PRETTY_PRINT));
                $this->info(json_encode($responses, JSON_PRETTY_PRINT));
                $this->updateOrder($trackingId, $deliveredTo, $deliveredAt);
            } else {
                $notDeliveredLog = $this->getNotDeliveredLog($logs);
                if ($trackingInfo['delivery_status'] !== $notDeliveredLog['Status']) {
                    $responses = $this->otherNotfication($trackingId, $whatsapp, $email);
                    Log::info(json_encode($responses, JSON_PRETTY_PRINT));
                    $this->info(json_encode($responses, JSON_PRETTY_PRINT));
                    $this->updateStatus($trackingId, $notDeliveredLog['Status']);
                }
            }
        } catch (\Exception $e) {
            Log::error("❌ Error tracking AWB $trackingId: " . $e->getMessage());
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

    private function updateOrder(string $trackingId, string $deliveredTo, ?Carbon $deliveredAt): void
    {
        $payload = [
            'delivery_status' => "POD",
            'delivered_to'    => $deliveredTo,
            'delivered_at'    => $deliveredAt ?? now(),
        ];
        Order::where('tracking_number', $trackingId)->update($payload);
        $this->counter++;
    }

    private function updateStatus(string $trackingId, $status): void
    {
        Order::where('tracking_number', $trackingId)->update(['delivery_status' => $status]);
        $this->counter++;
    }

    public function deliveredNotification($whatsapp, $email, $deliveredTo, $deliveredAt, $trackingId)
    {
        $formattedDate = date('F j, Y \a\t h:i A', strtotime($deliveredAt));

        $message = "Dear Customer,\n\nYour shipment has been successfully delivered to *$deliveredTo* on *$formattedDate*.\n\nThank you for choosing our service.";

        return $this->sendNotification('delivered', $whatsapp, $email, $message, "Order Delivered", $trackingId);
    }

    public function otherNotfication($trackingId, $whatsapp, $email)
    {
        $trackingUrl = "https://rozeskin.com/tracking/?tracking_id=$trackingId";

        $message = "Dear Customer,\n\n";
        $message .= "The status of your shipment has been updated to: Shipment Picked Up\n\n";
        $message .= "Track your shipment here:\n";
        $message .= "$trackingUrl\n\n";
        $message .= "Thank you for your continued trust.";

        return $this->sendNotification(
            'status',
            $whatsapp,
            $email,
            $message,
            "Shipment Status Update",
            $trackingId
        );
    }


    public function sendNotification($type, $whatsapp, $email, $message, $subject = null, $trackingId)
    {
        $responses = [];

        $normalizePhoneNumber = (new Controller)->normalizePhoneNumber($whatsapp);

        if ($normalizePhoneNumber) {
            $whatsappPayload = [
                'recipient' => $normalizePhoneNumber,
                'text' => $message, // ✅ Use the full message, not just the link
                'clientId' => $this->getClient(),
            ];

            WhastappSender::dispatch($whatsappPayload);

            $responses[] = ["whatsapp" => $whatsappPayload];
        }

        if ($email) {
            $emailPayload = [
                'recipient' => $email,
                'text' => $message,
                'subject' => $subject ?? ucfirst($type) . " Notification"
            ];

            if ($trackingId) {
                Mail::to($email)->queue(new TestMarkdownMail($trackingId));
            }
            $responses[] = ["email" => $emailPayload];
        }

        return $responses;
    }


    function getClient()
    {
        $clientId = WhatsappClient::value("accounts")[0]["clientId"] ?? "test";
        return $clientId;
    }
}
