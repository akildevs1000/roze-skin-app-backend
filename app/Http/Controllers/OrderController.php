<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\ValidationRequest;
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
        // Invoice::truncate();

        $validatedData = $request->validated();
        $customer = Customer::storeOrUpdateCustomerWithAddresses($validatedData);
        $validatedData["customer_id"] = $customer->id ?? 0;
        $validatedData["order_date"] = date("Y-m-d H:i:s");
        $order = Order::create($validatedData);
        return $order;
    }

    public function update(ValidationRequest $request, Order $order)
    {
        $validatedData = $request->validated();
        Customer::storeOrUpdateCustomerWithAddresses($validatedData);
        $order = $order->update($validatedData);
        return $order;
    }

    public function destroy(Order $Order)
    {
        $Order->delete();

        return response()->json();
    }
}
