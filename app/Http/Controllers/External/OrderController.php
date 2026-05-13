<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        $search = trim(request('search'));

        if (request('search') && ! is_numeric($search)) {
            return;
        }

        $order_status        = request('order_status');
        $customer_id         = request('customer_id');
        $business_source_id  = request('business_source_id');
        $delivery_service_id = request('delivery_service_id');
        $payment_method      = request('payment_method');

        $from = request('from');
        $to   = request('to');

        $perPage = request('per_page', 15);

        return Order::orderByDesc('id')
            ->when($search, function ($q) use ($search) {
                $q->where('order_id', $search);
                $q->orWhere('tracking_number', $search);
            })
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
            ->when($from && $to, function ($q) use ($from, $to) {
                $q->whereBetween('order_date', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->with(['business_source', 'delivery_service', 'invoice'])
            ->paginate($perPage);
    }
}
