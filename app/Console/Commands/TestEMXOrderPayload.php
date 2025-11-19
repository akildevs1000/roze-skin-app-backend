<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class TestEMXOrderPayload extends Command
{
    protected $signature = 'test:emx-payload {order_id=1581} {box_dimension=Small}';
    protected $description = 'Generate and log EMX payload without calling the API';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        $box = $this->argument('box_dimension');

        $this->info("Generating EMX payload for TEST ONLY — NO API CALL.");

        $order = Order::with(['customer', 'customer.shipping_address', 'delivery_service'])
            ->find($orderId);

        if (!$order) {
            $this->error("Order not found!");
            return;
        }

        $customer = $order->customer;

        // -------------------- BUILD PAYLOAD -------------------- //
        $data = [
            "weight" => [
                "value" => 250,
                "unit" => "Grams",
            ],

            "shipper" => [
                "contact" => [
                    "name" => "Roze Skincare",
                    "mobileNumber" => "0529048025",
                    "phoneNumber" => "0529048025",
                    "emailAddress" => "rozeskincaredubai@gmail.com",
                    "companyName" => "Roze Skincare",
                ],
                "address" => [
                    "line1" => "DRS JAFFER'S BLDG - SHOP NO 4 - Al Nahdha street - 83481 - Al Souq Al Kabeer",
                    "city" => "Dubai",
                    "countryCode" => "AE",
                    "zipCode" => "00000",
                ],
            ],

            "consignee" => [
                "contact" => [
                    "name" => $customer->full_name,
                    "mobileNumber" => $customer->whatsapp,
                    "phoneNumber" => $customer->phone,
                    "emailAddress" => $customer->email ?? "test@test.com",
                    "companyName" => $customer->full_name,
                ],
                "address" => [
                    "line1" => $customer->shipping_address->address_1 ?? "No Address given",
                    "city" => $customer->shipping_address->city ?? "No City given",
                    "countryCode" => "AE",
                    "zipCode" => "00000",
                ],
            ],

            // ✅ Using getBoxDimension() here
            "dimensions" => $this->getBoxDimension($box),

            "account" => [
                "number" => env("EMX_ACCOUNT_NO"),
            ],

            "productCode" => "Domestic",
            "serviceType" => "None",
            "printType" => "AWBOnly",
            "numberOfPieces" => 1,
            "referenceNumber1" => "any referece number",
            "specialNotes" => $order->special_instructions ?? "Fragile handle with care",
            "deliveryType" => "DoorToDoor",
            "contentType" => "NonDocument",
            "isCod" => $order->payment_method == 'COD',

            "coDAmount" => [
                "amount" => $order->total,
                "currency" => $order->currency ?? "AED",
            ],
        ];

        $result = json_encode($data,JSON_PRETTY_PRINT);

        // -------------------- LOG ONLY -------------------- //
        Log::channel('order_emx')->info("TEST EMX PAYLOAD (NO API CALL):");
        Log::channel('order_emx')->info($result);

        $this->info("Payload logged successfully to storage/logs/order_emx.log");

        return 0;
    }

    // -------------------- YOUR ADDED FUNCTION -------------------- //
    public function getBoxDimension($box = "Small")
    {
        $arr = [
            "Small" => [
                "length" => 23,
                "width" => 14,
                "height" => 4,
                "unit" => "Centimetre",
            ],
            "Medium" => [
                "length" => 35,
                "width" => 20,
                "height" => 15,
                "unit" => "Centimetre",
            ],
            "Large" => [
                "length" => 75,
                "width" => 35,
                "height" => 35,
                "unit" => "Centimetre",
            ]
        ];

        return $arr[$box] ?? $arr["Small"];
    }
}
