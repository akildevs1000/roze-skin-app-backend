<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\ValidationRequest;
use App\Jobs\BirthdayWishWhatsappCustomer;
use App\Jobs\SendEmail;
use App\Jobs\SendWhatsappMessage;
use App\Jobs\WhastappSender;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Template;
use App\Models\WhatsappClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    public function latestOrder()
    {
        //54083
        // Check if a specific order ID is requested

        $requestedOrderId = request('order');
        if ($requestedOrderId) {
            return Order::with(['business_source', 'delivery_service', 'invoice'])
                ->find($requestedOrderId);
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

        $order = Order::findOrFail(request('orderPrimaryId'));
        $order->tracking_number = request('airwayBillNumber') ?? '0';
        $order->save();

        $templates = Template::whereActionId(["action_id" => Template::ORDER_DISPATCHED])->orderBy("id", "desc")->get();

        if ($templates->isEmpty()) {

            return response()->json([
                "message" => "Trigger not found. Please go to Settings → Templates and create a new template for the 'Order Dispatched' trigger. Provide a name, select the 'Order Dispatched' action, and write the message in the description box. Same thing do this for email also if you want send notification as email also"
            ]);
        }


        $responses = [];

        $arr = $this->prepareMessage($templates, $order->customer, $order);


        if ($arr["whatsapp"]) {
            $normalizePhoneNumber = $this->normalizePhoneNumber($order->customer->whatsapp);
            if ($normalizePhoneNumber) {
                $whatsappPayload = [
                    'recipient' => $normalizePhoneNumber,
                    'text' => $arr["whatsapp"],
                    'clientId' => $this->getClient(),
                ];

                WhastappSender::dispatch($whatsappPayload);

                $responses[] = ["whatsapp" => $whatsappPayload];
            }
        }

        if ($arr["email"]) {
            $emailPayload = [
                'recipient' => $order->customer->email,
                'text' => $arr["email"],
                'subject' => "Order Received"
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

    public function index()
    {
        $search = trim(request('search'));

        if (request('search') && !is_numeric($search)) return;

        $status = request('status');

        $customer_id = request('customer_id');

        $business_source_id = request('business_source_id');
        $delivery_service_id = request('delivery_service_id');
        $payment_method = request('payment_method');


        $from = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        $dates = [$from, $to];

        $perPage = min((int) request('per_page', 15), 100); // Limit max results per page

        return Order::orderByDesc('id')
            ->when($search, function ($q) use ($search) {
                $q->where('order_id', $search)
                    ->orWhere('tracking_number', $search);
            })
            ->when($status, function ($q) use ($status) {
                $q->whereHas("invoice", fn($q) => $q->where('status', $status));
            })
            ->when(request('from') && request('to'), function ($q) use ($dates) {
                $q->whereBetween('order_date', $dates);
            })

            ->when($customer_id, function ($q) use ($customer_id) {
                $q->where('customer_id', $customer_id);
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

            ->with(['business_source', 'delivery_service', "invoice"])
            ->paginate($perPage);
    }

    public function stats()
    {
        $now = Carbon::now();
        $currentMonth = $now->month;

        // Get last month's stats from cache or compute and store them
        $lastMonthStats = Cache::remember('order_stats_last_month', now()->addDays(1), function () use ($now) {
            $lastMonth = $now->copy()->subMonth()->month;

            return [
                'orders' => Order::whereMonth('created_at', $lastMonth)->count(),
                'income' => Order::whereMonth('created_at', $lastMonth)->sum('total'),
            ];
        });

        // Real-time data
        $ordersThisMonth = Order::whereMonth('created_at', $currentMonth)->count();
        $incomeThisMonth = Order::whereMonth('created_at', $currentMonth)->sum('total');
        $pendingOrders = Order::whereDoesntHave('invoice')->count();
        $totalOrders = Order::count();

        return [
            [
                'label' => 'Last Month / Current Month (Orders)',
                'icon' => 'mdi-cart-outline',
                'value' => "{$lastMonthStats['orders']} / $ordersThisMonth",
                'color' => 'blue',
            ],
            [
                'label' => 'Total Orders',
                'icon' => 'mdi-calendar-today',
                'value' => $totalOrders,
                'color' => 'indigo',
            ],
            [
                'label' => 'Pending Order',
                'icon' => 'mdi-clock-outline',
                'value' => $pendingOrders,
                'color' => 'orange',
            ],
            [
                'label' => 'Last Month / Current Month (Income)',
                'icon' => 'mdi-currency-usd',
                'value' => "{$lastMonthStats['income']} / $incomeThisMonth",
                'color' => 'green',
            ],
        ];
    }


    public function store(ValidationRequest $request)
    {

        // order_id => 53449
        // create order with same order id from website
        // https://rozeskin.com/checkout/order-received/53449/?key=wc_order_Wa2sCxZ1pCSJY

        $validatedData = $request->validated();

        if ($validatedData['order_id'] > 0 && Order::where('order_id', $validatedData['order_id'])->exists()) {
            return response()->json([
                'message' => 'Order Id ' . $validatedData['order_id'] . ' already exists.',
            ], 409);
        }

        $customer = Customer::storeOrUpdateCustomerWithAddresses($validatedData);
        $validatedData["customer_id"] = $customer->id ?? 0;
        $validatedData["order_date"] = date("Y-m-d H:i:s");
        $order = Order::create($validatedData);

        $templates = Template::whereActionId(["action_id" => Template::ORDER_RECEIVED])->orderBy("id", "desc")->get();

        if (!count($templates)) {
            return $order;
        }

        $responses = [];

        $arr = $this->prepareMessage($templates, $customer, $order);

        if ($arr["whatsapp"]) {
            $normalizePhoneNumber = $this->normalizePhoneNumber($customer->whatsapp);
            if ($normalizePhoneNumber) {
                $whatsappPayload = [
                    'recipient' => $normalizePhoneNumber,
                    'text' => $arr["whatsapp"],
                    'clientId' => $this->getClient(),
                ];

                WhastappSender::dispatch($whatsappPayload);

                $responses[] = ["whatsapp" => $whatsappPayload];
            }
        }

        if ($arr["email"]) {
            $emailPayload = [
                'recipient' => $customer->email,
                'text' => $arr["email"],
                'subject' => "Order Received"
            ];

            SendEmail::dispatch($emailPayload);

            $responses[] = ["email" => $emailPayload];
        }

        return $responses;
    }

    public function update(ValidationRequest $request, Order $order)
    {
        $validatedData = $request->validated();
        Customer::storeOrUpdateCustomerWithAddresses($validatedData);
        $order = $order->update($validatedData);
        return $order;
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

    function prepareMessage($templates, $customer, $order)
    {
        $full_name = $customer->full_name;
        $order_id = $order->order_id > 0 ? $order->order_id : $order->id;
        $items = collect($order->items)->pluck('item')->implode(', ');
        $total = $order->total;
        $shipping_address = $customer->shipping_address->full_address;
        $tracking_number = $order->tracking_number;


        // $message = "Dear $full_name\n\n"
        //     . "Thank you for your order!\n\n"
        //     . "Order ID: $order_id\n"
        //     . "Items: $items\n"
        //     . "Total: AED $total\n"
        //     . "Shipping Address: $shipping_address\n\n"
        //     . "We have received your order and it’s currently being processed\n"
        //     . "We will notify you once it has been shipped.\n\n"
        //     . "Team RozeSkin";


        $whatsapp = null;
        $email = null;

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
                        $tracking_number
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
                        $tracking_number
                    ],
                    $messageBody
                );

                $email = preg_replace('/<p>(.*?)<\/p>/s', "$1\n", $email); // Convert <p> to new lines

                $email = strip_tags($email); // Ensure no remaining tags

            }
        }

        return ["whatsapp" => trim($whatsapp), "email" => trim($email)];
    }

    function getClient()
    {
        $clientId = WhatsappClient::value("accounts")[0]["clientId"] ?? "test";
        return $clientId;
    }
}
