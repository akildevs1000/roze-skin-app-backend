<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\ValidationRequest;
use App\Jobs\SendWhatsappMessage;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;

class OrderController extends Controller
{
    public function dropDown()
    {
        return Order::orderByDesc('id')->get();
    }

    public function index()
    {
        $search = trim(request('search'));
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
            ->when(count($dates) > 0, function ($q) use ($dates) {
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

    public function store(ValidationRequest $request)
    {

        // order_id => 53449
        // create order with same order id from website
        // https://rozeskin.com/checkout/order-received/53449/?key=wc_order_Wa2sCxZ1pCSJY

        $validatedData = $request->validated();

        if (Order::where('order_id', $validatedData['order_id'])->exists()) {
            return response()->json([
                'message' => 'Order Id ' . $validatedData['order_id'] . ' already exists.',
            ], 409);
        }

        $customer = Customer::storeOrUpdateCustomerWithAddresses($validatedData);
        $validatedData["customer_id"] = $customer->id ?? 0;
        $validatedData["order_date"] = date("Y-m-d H:i:s");
        $order = Order::create($validatedData);
        $validatedData['customer']['whatsapp'] = "971554501483";

        $order_id = $order->order_id > 0 ? $order->order_id : $order->id;
        $full_name = $customer->full_name;
        $shipping_address = $customer->shipping_address->full_address;
        $total = $order->total;
        $items = collect($order->items)->pluck('item')->implode(', ');

        $message = "Dear $full_name\n\n"
            . "Thank you for your order!\n\n"
            . "Order ID: $order_id\n"
            . "Items: $items\n"
            . "Total: AED $total\n"
            . "Shipping Address: $shipping_address\n\n"
            . "We have received your order and it’s currently being processed\n"
            . "We will notify you once it has been shipped.\n\n"
            . "Team RozeSkin";

        SendWhatsappMessage::dispatch($validatedData['customer']['whatsapp'], $message);
        
        return $order;
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
}
