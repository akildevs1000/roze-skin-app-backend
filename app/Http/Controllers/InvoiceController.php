<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\ValidationRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function dropDown()
    {
        return Invoice::orderByDesc('id')->get();
    }

    public function index()
    {
        $search = trim(request('search'));

        $status = request('status');

        $payment_method = request('payment_method');

        $customer_id = request('customer_id');

        $delivery_service_id = request('delivery_service_id');

        $from = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to   = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        $dates = [$from, $to];

        $perPage = min((int) request('per_page', 15), 100); // Limit max results per page

        return Invoice::with([
            'customer.shipping_address',
            'customer.billing_address',
            'payments.payment_mode',
            'order.delivery_service',
            'order.business_source',
        ])

            ->when($search, function ($q) use ($search) {

                $order_id = Order::where("order_id", $search)->value("id");

                if ($order_id) {
                    $q->where('order_id', $order_id);
                } else {
                    // check it value is less then 1000 and remove all the zeros
                    $q->where('id', env("WILD_CARD") ?? 'ILIKE', '%' . ltrim($search, '0') . '%')
                        ->orWhereHas('order', function ($q2) use ($search) {
                            $q2->where('tracking_number', $search);
                        });
                }
            })

            ->when($customer_id, function ($q) use ($customer_id) {
                $q->whereHas('order', function ($q) use ($customer_id) {
                    $q->where('customer_id', $customer_id);
                });
            })

            ->when($delivery_service_id, function ($q) use ($delivery_service_id) {
                $q->whereHas('order', function ($q) use ($delivery_service_id) {
                    $q->where('delivery_service_id', $delivery_service_id);
                });
            })

            ->when($payment_method, function ($q) use ($payment_method) {
                $q->whereHas('order', function ($q) use ($payment_method) {
                    $q->where('payment_method', $payment_method);
                });
            })

            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })

            ->whereBetween('created_at', $dates)

            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function stats()
    {
        $now          = Carbon::now();
        $currentMonth = $now->month;

        // Get last month's stats from cache or compute and store them
        $lastMonthStats = Cache::remember('invoice_stats_last_month', now()->addDays(1), function () use ($now) {
            $lastMonth = $now->copy()->subMonth()->month;

            return [
                'invoices' => Invoice::whereMonth('created_at', $lastMonth)->count(),
                'income'   => Order::whereHas("invoice")->whereMonth('created_at', $lastMonth)->sum('total'), // this is working fine but i want from invoice from
            ];
        });

        // Real-time data
        $ordersThisMonth = Invoice::whereMonth('created_at', $currentMonth)->count();
        $incomeThisMonth = Order::whereHas("invoice")->whereMonth('created_at', $currentMonth)->sum('total');
        $totalOrders     = Invoice::count();

        return [
            [
                'label' => 'Last Month / Current Month (Invoice)',
                'icon'  => 'mdi-cart-outline',
                'value' => "{$lastMonthStats['invoices']} / $ordersThisMonth",
                'color' => 'blue',
            ],
            [
                'label' => 'Total Orders',
                'icon'  => 'mdi-calendar-today',
                'value' => $totalOrders,
                'color' => 'indigo',
            ],
            [
                'label' => 'Last Month / Current Month (Income)',
                'icon'  => 'mdi-currency-usd',
                'value' => "{$lastMonthStats['income']} / $incomeThisMonth",
                'color' => 'green',
            ],
        ];
    }

    public function store(ValidationRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // Prepare invoice payload
            $invoiceData = [
                'customer_id'             => $validated['customer_id'],
                'order_id'                => $validated['order_id'],
                'status'                  => $validated['status'],
                'converted_to_invoice_at' => now(),
            ];

            // Create or update the invoice
            $invoice = Invoice::updateOrCreate(
                ['order_id' => $validated['order_id']],
                $invoiceData
            );

            Order::where('id', $validated['order_id'])->update([
                // 'total' => $validated['total'] ?? 0,
                // 'discount' => $validated['discount'] ?? 0,
                'order_status' => "completed",
            ]);

            $this->handleEMXOrder($request->order_id, $request->box_dimension);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invoice stored successfully.',
                'data'    => $invoice,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while storing the invoice.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Invoice $Invoice)
    {
        // Validate incoming request data
        $validated = $request->validate([
            'business_source_id'   => 'required|integer|exists:business_sources,id',
            'delivery_service_id'  => 'required|integer|exists:delivery_services,id',
            'tracking_number'      => 'nullable|max:255',
            'payment_method'       => 'required|string|max:100',
            'payment_method_title' => 'nullable|string|max:255',
            'paid_amount'          => 'required|numeric|min:0',
            'order_id'             => 'required',
            'status'               => 'required',
        ]);

        $orderPayload = [
            "business_source_id"   => $validated['business_source_id'],
            "delivery_service_id"  => $validated['delivery_service_id'],
            "tracking_number"      => $validated['tracking_number'] ?? 0,
            "payment_method"       => $validated['payment_method'],
            "payment_method_title" => $validated['payment_method_title'],
            "paid_amount"          => $validated['paid_amount'],
        ];

        Order::where("id", $validated['order_id'])->update($orderPayload);

        $Invoice->update(['status' => $validated['status']]);

        return $Invoice;
    }

    public function destroy(Invoice $Invoice)
    {
        $Invoice->delete();

        return response()->json();
    }

    public function handleEMXOrder($order_id, $box_dimension = "Small")
    {
        Log::channel('order_emx')->info('EMX Payload Start:', $order_id);

        $order = Order::with("delivery_service")->find($order_id);

        if (!$order) {
            Log::channel('order_emx')->info("Order not found: $order_id");
            Log::channel('order_emx')->info('EMX Payload End:', $order_id);
            return;
        }

        if ($order?->delivery_service?->name !== "EMX") {
            Log::channel('order_emx')->info("Order delivery server is not EMX: $order_id");
            Log::channel('order_emx')->info('EMX Payload End:', $order_id);
            return;
        }

        $customer =  $order->customer;

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
                    "line1" => $customer?->shipping_address?->address_1 ?? "No Address given",
                    "city" => $customer?->shipping_address?->city ?? "No City given",
                    "countryCode" => "AE",
                    "zipCode" => "00000",
                ],
            ],

            "dimensions" => $this->getBoxDimension($box_dimension ?? "Small"),

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

        $headers = [
            'Content-Type' => 'application/json',
            'x-api-key'    => env('EMX_ORDER_CREATE_API_KEY'),
        ];

        $options = [
            'max_body_length' => -1,      // unlimited
            'max_content_length' => -1,   // unlimited
        ];

        Log::channel('order_emx')->info("EMX Payload");
        Log::channel('order_emx')->info(json_encode($data,JSON_PRETTY_PRINT));

        try {
            $response = Http::withOptions($options)
                ->withHeaders($headers)
                ->post(env('EMX_BASE_URL') . "/Shipments/create", $data);

            if (!$response->successful()) {
                Log::channel('order_emx')->error('EMX API returned an error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                Log::channel('order_emx')->info('EMX Payload End:', $order_id);

                return false;
            }

            Log::channel('order_emx')->info('EMX API request successful', [
                'response' => $response->json(),
            ]);

            Log::channel('order_emx')->info('EMX Payload End:', $order_id);

            return $response->json();
        } catch (\Exception $e) {
            Log::channel('order_emx')->error('EMX API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Log::channel('order_emx')->info('EMX Payload End:', $order_id);

            return false;
        }

        Log::channel('order_emx')->info('EMX Payload End:', $order_id);

        return $data;
    }

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
        return $arr[$box] ?? $arr["Small"]; // fallback to Small if invalid
    }
}
