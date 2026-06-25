<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\ValidationRequest;
use App\Jobs\SendEmail;
use App\Jobs\WhastappSender;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Template;
use App\Models\WhatsappClient;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function latestOrder()
    {
        //54083
        // Check if a specific order ID is requested

        $requestedOrderId = request('order');
        if ($requestedOrderId) {
            return Order::with(['business_source', 'delivery_service', 'invoice'])
                ->where("order_id", $requestedOrderId)->first();
        }

        $orderId = Invoice::whereNotNull('converted_to_invoice_at')
            ->latest('converted_to_invoice_at')
            ->value('order_id');

        return $orderId
            ? Order::with(['business_source', 'delivery_service', 'invoice'])->find($orderId)
            : null;
    }

    public function orderCreateAcknowledge()
    {

        $order                  = Order::findOrFail(request('orderPrimaryId'));
        $order->tracking_number = request('airwayBillNumber') ?? '0';
        $order->save();

        $templates = Template::whereActionId(["action_id" => Template::ORDER_DISPATCHED])->orderBy("id", "desc")->get();

        if ($templates->isEmpty()) {

            return response()->json([
                "message" => "Trigger not found. Please go to Settings → Templates and create a new template for the 'Order Dispatched' trigger. Provide a name, select the 'Order Dispatched' action, and write the message in the description box. Same thing do this for email also if you want send notification as email also",
            ]);
        }

        $responses = [];

        $arr = $this->prepareMessage($templates, $order->customer, $order);

        if ($arr["whatsapp"]) {
            $normalizePhoneNumber = $this->normalizePhoneNumber($order->customer->whatsapp);
            if ($normalizePhoneNumber) {
                $whatsappPayload = [
                    'recipient' => $normalizePhoneNumber,
                    'text'      => $arr["whatsapp"],
                    'clientId'  => $this->getClient(),
                ];

                WhastappSender::dispatch($whatsappPayload);

                $responses[] = ["whatsapp" => $whatsappPayload];
            }
        }

        if ($arr["email"]) {
            $emailPayload = [
                'recipient' => $order->customer->email,
                'text'      => $arr["email"],
                'subject'   => "Order Received",
            ];

            SendEmail::dispatch($emailPayload);

            $responses[] = ["email" => $emailPayload];
        }

        return $responses;
    }

    public function dropDown()
    {
        return Order::orderByDesc('id')->get();
    }

    public function getStatusesDropdown()
    {
        return Order::getStatuses();
    }

    public function index()
    {
        $search = trim(request('search'));

        if (request('search') && ! is_numeric($search)) {
            return;
        }

        $order_status = request('order_status');

        $customer_id = request('customer_id');

        $business_source_id  = request('business_source_id');
        $delivery_service_id = request('delivery_service_id');
        $payment_method      = request('payment_method');

        $from = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to   = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        $dates = [$from, $to];

        $perPage = request('per_page', 15); // Limit max results per page

        return Order::orderByDesc('id')
            ->when($search, function ($q) use ($search) {
                $q->where('order_id', $search);
                $q->orWhere('tracking_number', $search);
            })
            // ->when($order_status != "completed", function ($q) use ($order_status) {
            //     $q->whereHas("invoice", fn($q) => $q->where('status', $order_status));
            // })

            ->when($customer_id, function ($q) use ($customer_id) {
                $q->where('customer_id', $customer_id);
            })

            ->when($order_status, function ($q) use ($order_status) {
                $q->where('order_status', $order_status);
            })

            ->when($business_source_id, function ($q) use ($business_source_id) {
                $q->where('business_source_id', $business_source_id);
            })

            ->when($delivery_service_id, function ($q) use ($delivery_service_id) {
                $q->where('delivery_service_id', $delivery_service_id);
            })

            ->when($payment_method, function ($q) use ($payment_method) {
                $q->where('payment_method', $payment_method);
            })

            ->when(! $search, function ($q) use ($dates) {
                $q->whereBetween('order_date', $dates);
            })

            ->with(['business_source', 'delivery_service', 'invoice'])

            ->paginate($perPage);
    }

    public function stats()
    {
        $now          = Carbon::now();
        $today        = $now->toDateString();
        $currentMonth = $now->month;

        // Cache last month's stats
        $lastMonthStats = Cache::remember('order_stats_last_month', now()->addDay(), function () use ($now) {
            $lastMonth = $now->copy()->subMonth()->month;

            return [
                'orders' => Order::whereMonth('created_at', $lastMonth)->count(),
                'income' => Order::whereMonth('created_at', $lastMonth)->sum('total'),
            ];
        });

        // Monthly stats
        $ordersThisMonth = Order::whereMonth('created_at', $currentMonth)->count();
        $incomeThisMonth = Order::whereMonth('created_at', $currentMonth)->sum('total');

        // Daily stats (NEW)
        $ordersToday = Order::whereDate('created_at', $today)->count();
        $incomeToday = Order::whereDate('created_at', $today)->sum('total');

        // Other stats
        $pendingOrders = Order::whereDoesntHave('invoice')->count();
        $totalOrders   = Order::count();

        return [
            [
                'label' => 'Today Orders',
                'icon'  => 'mdi-calendar-today',
                'value' => $ordersToday,
                'color' => 'teal',
            ],
            [
                'label' => 'Today Income',
                'icon'  => 'mdi-currency-usd',
                'value' => $incomeToday,
                'color' => 'green',
            ],
            [
                'label' => 'Last Month / Current Month (Orders)',
                'icon'  => 'mdi-cart-outline',
                'value' => "{$lastMonthStats['orders']} / $ordersThisMonth",
                'color' => 'blue',
            ],
            [
                'label' => 'Total Orders',
                'icon'  => 'mdi-calendar-multiple',
                'value' => $totalOrders,
                'color' => 'indigo',
            ],
            [
                'label' => 'Pending Orders',
                'icon'  => 'mdi-clock-outline',
                'value' => $pendingOrders,
                'color' => 'orange',
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
        try {

            // order_id => 53449
            // create order with same order id from website
            // https://rozeskin.com/checkout/order-received/53449/?key=wc_order_Wa2sCxZ1pCSJY

            $validatedData = $request->validated();

            $paymentMethod = strtolower($validatedData['payment_method'] ?? '');
            $orderStatus   = strtolower($validatedData['order_status'] ?? 'failed');
            $orderId       = $validatedData['order_id'] ?? null;

            // ❌ Block failed orders — but COD never pays online, so a "failed"
            // status from the website is not a real payment failure for COD.
            if ($orderStatus === 'failed' && $paymentMethod !== 'cod') {
                return $this->errorResponse(
                    'Order cannot be created: failed status',
                    $request,
                    409
                );
            }

            // ❌ Block invalid pending orders (non-COD)
            if ($orderStatus === 'pending' && $paymentMethod !== 'cod') {
                return $this->errorResponse(
                    'Order cannot be created: pending payment is only allowed for Cash on Delivery (COD).',
                    $request,
                    409
                );
            }

            // ❌ Prevent duplicate order
            if ($orderId && Order::where('order_id', $orderId)->exists()) {
                return $this->errorResponse(
                    "Order ID {$orderId} already exists.",
                    $request,
                    409
                );
            }

            // Orders keep their own address and must not overwrite the customer's saved one.
            $customer                     = Customer::storeOrUpdateCustomerWithAddresses($validatedData, null, false);
            $validatedData["customer_id"] = $customer->id ?? 0;
            $validatedData["order_date"]  = request("order_date", date("Y-m-d H:i:s"));
            $validatedData["order_status"]  = $paymentMethod === 'cod' ? 'processing' : $orderStatus;
            $order                        = Order::create($validatedData);

            // Freeze this order's own copy of the address (keeps history per order).
            // Customer::storeOrderAddresses(
            //     $customer->id,
            //     $order->id,
            //     $validatedData['shipping_address'] ?? [],
            //     $validatedData['billing_address'] ?? []
            // );

            $templates = Template::whereActionId(["action_id" => Template::ORDER_RECEIVED])->orderBy("id", "desc")->get();

            if (! count($templates)) {
                return $order;
            }

            $responses = [];

            $arr = $this->prepareMessage($templates, $customer, $order);

            if ($arr["whatsapp"]) {
                $normalizePhoneNumber = $this->normalizePhoneNumber($customer->whatsapp);
                if ($normalizePhoneNumber) {
                    $whatsappPayload = [
                        'recipient' => $normalizePhoneNumber,
                        'text'      => $arr["whatsapp"],
                        'clientId'  => $this->getClient(),
                    ];

                    WhastappSender::dispatch($whatsappPayload);

                    $responses[] = ["whatsapp" => $whatsappPayload];
                }
            }

            if ($arr["email"]) {
                $emailPayload = [
                    'recipient' => $customer->email,
                    'text'      => $arr["email"], // message body
                    'subject'   => "Order Received",
                ];

                SendEmail::dispatch($emailPayload);

                $responses[] = ["email" => $emailPayload];
            }

            Log::channel('orders')->info(json_encode(["request" => $request->all(), "response" => $order], JSON_PRETTY_PRINT));

            return $responses;
        } catch (\Exception $e) {
            Log::channel('orders')->info(json_encode(["request" => $request->all(), "response" => $e->getMessage()], JSON_PRETTY_PRINT));
        }
    }


    public function WhatsappStore(ValidationRequest $request)
    {
        try {

            $validatedData = $request->validated();
            // Orders keep their own address and must not overwrite the customer's saved one.
            $customer                     = Customer::storeOrUpdateCustomerWithAddresses($validatedData, null, false);
            $validatedData["customer_id"] = $customer->id ?? 0;
            $validatedData["order_date"]  = date("Y-m-d H:i:s");
            $order                        = Order::create($validatedData);
            $order->order_id = "1000" . $order->id;
            $order->save();

            // Freeze this order's own copy of the address (keeps history per order).
            Customer::storeOrderAddresses(
                $customer->id,
                $order->id,
                $validatedData['shipping_address'] ?? [],
                $validatedData['billing_address'] ?? []
            );

            $templates = Template::whereActionId(["action_id" => Template::ORDER_RECEIVED])->orderBy("id", "desc")->get();

            if (! count($templates)) {
                return $order;
            }

            $responses = [];

            $arr = $this->prepareMessage($templates, $customer, $order);

            if ($arr["whatsapp"]) {
                $normalizePhoneNumber = $this->normalizePhoneNumber($customer->whatsapp);
                if ($normalizePhoneNumber) {
                    $whatsappPayload = [
                        'recipient' => $normalizePhoneNumber,
                        'text'      => $arr["whatsapp"],
                        'clientId'  => $this->getClient(),
                    ];

                    WhastappSender::dispatch($whatsappPayload);

                    $responses[] = ["whatsapp" => $whatsappPayload];
                }
            }

            if ($arr["email"]) {
                $emailPayload = [
                    'recipient' => $customer->email,
                    'text'      => $arr["email"], // message body
                    'subject'   => "Order Received",
                ];

                SendEmail::dispatch($emailPayload);

                $responses[] = ["email" => $emailPayload];
            }

            Log::channel('orders')->info(json_encode(["request" => $request->all(), "response" => $order], JSON_PRETTY_PRINT));

            return $responses;
        } catch (\Exception $e) {
            Log::channel('orders')->info(json_encode(["request" => $request->all(), "response" => $e->getMessage()], JSON_PRETTY_PRINT));
        }
    }

    public function update(ValidationRequest $request, Order $order)
    {
        $validatedData = $request->validated();
        // Orders keep their own address and must not overwrite the customer's saved one.
        Customer::storeOrUpdateCustomerWithAddresses($validatedData, null, false);
        $order->update($validatedData);

        // The order's address is frozen at creation — editing never changes it.

        return $order;
    }

    public function cancelOrder()
    {
        try {
            $orderId      = request("order_id");
            $invoice_id   = request("invoice_id");
            $cancelReason = request("cancel_reason");

            $this->recordLog("Cancel order request received.");

            $order = Order::where("order_id", $orderId)->first();

            if (! $order) {

                $this->recordLog("Order not found.");

                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                ], 404);
            }

            if ($invoice_id) {

                $invoice = Invoice::where("id", $invoice_id)->first();

                if (! $invoice) {
                    $this->recordLog("Invoice not found.");
                    return response()->json([
                        'success' => false,
                        'message' => 'Invoice not found.',
                    ], 404);
                }

                $invoice->update([
                    "status" => 'Cancelled',
                ]);
            }

            $order->update([
                "order_status"  => 'cancelled',
                "cancel_reason" => $cancelReason,
            ]);

            // Return any stock that was deducted when this order became an invoice.
            try {
                $invoiceToReverse = $invoice ?? $order->invoice;
                if ($invoiceToReverse) {
                    app(\App\Services\StockSyncService::class)->reverseForInvoice($invoiceToReverse);
                }
            } catch (\Throwable $e) {
                $this->recordLog("Stock reversal failed: " . $e->getMessage());
            }

            $this->recordLog("Order cancelled successfully.");

            return response()->json([
                'success' => true,
                'message' => 'Order has been cancelled.',
                'order'   => $order,
            ]);
        } catch (\Exception $e) {
            $this->recordLog("Error while cancelling order.");

            return response()->json([
                'success' => false,
                'message' => "Service Error",
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $order = Order::where("id", $id)->first();
        if ($order) {
            $order->delete();
            return response()->noContent();
        }

        return 500;
    }

    public function prepareMessage($templates, $customer, $order)
    {
        $full_name        = $customer->full_name;
        $order_id         = $order->order_id > 0 ? $order->order_id : $order->id;
        $items            = collect($order->items)->pluck('item')->implode(', ');
        $total            = $order->total;
        // Prefer the address frozen onto this order; fall back to the customer's current one.
        $shipping_address = optional($order->shippingAddress ?? $customer->shipping_address)->full_address;
        $tracking_number  = $order->tracking_number;

        $whatsapp = null;
        $email    = null;

        foreach ($templates as $key => $template) {

            $messageBody = $template->body;

            if ($template->medium == "whatsapp") {

                $whatsapp = str_replace(
                    ['[full_name]', '[order_id]', '[items]', '[total]', '[shipping_address]', '[tracking_number]'],
                    [
                        $full_name,
                        $order_id,
                        $items,
                        $total,
                        $shipping_address,
                        $tracking_number,
                    ],
                    $messageBody
                );

                $whatsapp = preg_replace('/<p>(.*?)<\/p>/s', "$1\n", $whatsapp); // Convert <p> to new lines

                $whatsapp = strip_tags($whatsapp); // Ensure no remaining tags

            }

            if ($template->medium == "email") {

                $email = str_replace(
                    ['[full_name]', '[order_id]', '[items]', '[total]', '[shipping_address]', '[tracking_number]'],
                    [
                        $full_name,
                        $order_id,
                        $items,
                        $total,
                        $shipping_address,
                        $tracking_number,
                    ],
                    $messageBody
                );

                $email = preg_replace('/<p>(.*?)<\/p>/s', "$1\n", $email); // Convert <p> to new lines

                $email = strip_tags($email); // Ensure no remaining tags

            }
        }

        return ["whatsapp" => trim($whatsapp), "email" => trim($email)];
    }

    public function getClient()
    {
        $clientId = WhatsappClient::value("accounts")[0]["clientId"] ?? "test";
        return $clientId;
    }

    public function recordLog($message)
    {

        $orderId      = request("order_id");
        $invoice_id   = request("invoice_id");
        $cancelReason = request("cancel_reason");

        $logPayload = [
            "order_id"      => $orderId,
            "invoice_id"    => $invoice_id,
            "cancel_reason" => $cancelReason,
        ];

        Log::channel('order_cancel')->info($message, $logPayload);
    }

    public function orderQtyByDate(Request $request)
    {
        $from = $request->query('from_date', date("Y-m-01"));
        $to   = $request->query('to_date', date("Y-m-t"));

        $query = Order::selectRaw('DATE(order_date) as date, COUNT(*) as total')
            ->whereIN('order_status', [Order::COMPLETED, Order::PROCESSING])
            ->whereDate('order_date', '>=', $from)
            ->whereDate('order_date', '<=', $to)
            ->groupBy('date')
            ->orderBy('date');

        return $query->get()->toArray();
    }

    public function orderSumByDate(Request $request)
    {
        $from = $request->query('from_date', date("Y-m-01"));
        $to   = $request->query('to_date', date("Y-m-t"));

        $query = Order::selectRaw('DATE(order_date) as date, SUM(total) as total')
            ->whereIN('order_status', [Order::COMPLETED, Order::PROCESSING])
            ->whereDate('order_date', '>=', $from)
            ->whereDate('order_date', '<=', $to)
            ->groupBy('date')
            ->orderBy('date');

        return $query->get()->toArray();
    }

    public function orderSumByDate_OLD(Request $request)
    {

        $from = request('from_date') ?? date('Y-m-d');
        $to   = request('to_date') ?? date('Y-m-d');

        $orders = Order::with('invoice')
            ->whereHas('invoice', fn($q) => $q->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to))
            ->get()
            ->groupBy(fn($order) => $order->invoice->created_at->format('Y-m-d'))
            ->map(fn($orders, $date) => [
                'date'  => $date,
                'total' => $orders->sum(fn($o) => $o->total ?? 0),
            ])
            ->toArray();

        // Generate all dates in the range
        $period = CarbonPeriod::create($from, $to);

        $result = [];
        foreach ($period as $date) {
            $day      = $date->format('Y-m-d');
            $result[] = [
                'date'  => $day,
                'total' => $orders[$day]['total'] ?? 0, // 0 if no orders on that date
            ];
        }

        return $result;
    }

    public function statsByDate(Request $request)
    {
        $from = $request->query('from_date');
        $to   = $request->query('to_date');

        $query = Order::query();

        // Default to first and last day of current month if not provided
        $query->whereDate('created_at', '>=', $from ?? date("Y-m-01"));
        $query->whereDate('created_at', '<=', $to ?? date("Y-m-t"));

        $totalOrders     = $query->clone()->whereNot("order_status", "cancelled")->count();
        $cancelledOrders = $query->clone()->where("order_status", "cancelled")->count();
        $totalOrdersSum  = $query->sum("total");

        return [
            [
                'label' => 'Total Orders',
                'icon'  => 'mdi-cart-outline',
                'value' => $totalOrders,
                'color' => 'indigo',
            ],
            [
                'label' => 'Cancelled Orders',
                'icon'  => 'mdi-cancel',
                'value' => $cancelledOrders,
                'color' => 'orange',
            ],
            [
                'label' => 'Total (Income)',
                'icon'  => 'mdi-currency-usd',
                'value' => $totalOrdersSum,
                'color' => 'green',
            ],
        ];
    }

    private function errorResponse(string $message = "", $request, int $status = 409)
    {
        $response = ['message' => $message];

        Log::channel('orders')->info(json_encode([
            "request"  => $request->all(),
            "response" => $response
        ], JSON_PRETTY_PRINT));

        return response()->json($response, $status);
    }
}
