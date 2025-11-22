<?php

namespace App\Http\Controllers;

use App\Models\BusinessSource;
use App\Models\Order;
use App\Models\Product;
use Carbon\CarbonPeriod;

class ReportController extends Controller
{
    public function products()
    {
        $from   = request('from') ? request('from') : date("Y-m-d");
        $to     = request('to') ? request('to') : date("Y-m-d");
        $orders = $this->getOrders($from, $to);
        $period = collect(CarbonPeriod::create($from, $to))->map(fn($d) => $d->format('Y-m-d'));
        return $this->getProducts($orders, $period);
    }

    public function getProducts($orders, $period)
    {
        $data = [];

        $products = Product::when(request()->filled('product_id'), function ($query) {
            $query->where('id', request('product_id'));
        })->get(["item_number", "description"])->keyBy('description');

        foreach ($period as $date) {
            $ordersForDate = $orders->filter(function ($order) use ($date) {
                return date("Y-m-d", strtotime($order->order_date)) === $date;
            });

            foreach ($products as $product) {
                $totalQuantity = 0;
                $totalPrice    = 0;

                foreach ($ordersForDate as $order) {
                    foreach ($order->items as $item) {
                        if ($item['item'] === $product->description) {
                            $totalQuantity += $item['quantity'];
                            $totalPrice += $item['total'];
                        } else {
                            info($item['item']);
                        }
                    }
                }

                $data[date("d M", strtotime($date))][$product->item_number ?? "---"] = [
                    'item_code' => $product->item_number ?? "---",
                    'product'   => $product->item_number ?? "---",
                    'price'     => number_format($totalPrice ?? 0, 2),
                    'quantity'  => $totalQuantity ?? 0,
                ];
            }
        }

        return $data;
    }

    public function payment_modes()
    {
        $from   = request('from') ? request('from') : date("Y-m-d");
        $to     = request('to') ? request('to') : date("Y-m-d");
        $orders = $this->getOrders($from, $to);
        $period = collect(CarbonPeriod::create($from, $to))->map(fn($d) => $d->format('Y-m-d'));
        return $this->getPaymentModes($orders, $period);
    }

    public function sources()
    {
        $from   = request('from') ? request('from') : date("Y-m-d");
        $to     = request('to') ? request('to') : date("Y-m-d");
        $orders = $this->getOrders($from, $to);
        $period = collect(CarbonPeriod::create($from, $to))->map(fn($d) => $d->format('Y-m-d'));
        return $this->getSources($orders, $period);
    }

    public function getSources($orders, $period)
    {
        // All business sources from DB
        $businessSources = BusinessSource::pluck('name');

        $data = [];

        foreach ($period as $date) {
            $data[$date] = [];

            foreach ($businessSources as $sourceName) {
                // Filter orders for this date + business source
                $filtered = $orders->filter(function ($order) use ($date, $sourceName) {
                    $orderSource = $order->business_source ? $order->business_source->name : 'Unknown';
                    return date("Y-m-d", strtotime($order->order_date)) === $date
                        && $orderSource === $sourceName;
                });

                $totalQuantity = 0;
                $totalPrice    = 0;

                foreach ($filtered as $order) {
                    foreach ($order->items as $item) {
                        $totalQuantity += $item['quantity'];
                        $totalPrice += $item['total'];
                    }
                }

                $data[$date][$sourceName] = [
                    'price'    => number_format($totalPrice, 2),
                    'quantity' => $totalQuantity,
                ];
            }
        }

        return $data;
    }

    public function getPaymentModes($orders, $period)
    {
        $data = [];

        // Get all payment methods from existing orders
        $paymentMethods = $orders->pluck('payment_method')
            ->map(fn($m) => strtoupper($m))
            ->unique()
            ->values();

        // If no orders, optionally define your known payment methods
        if ($paymentMethods->isEmpty()) {
            $paymentMethods = collect(['CASH', 'CARD', 'COD']); // add more as needed
        }

        foreach ($period as $date) {
            $data[$date] = [];

            foreach ($paymentMethods as $method) {
                // Filter orders for this date + method
                $filtered = $orders->filter(function ($order) use ($date, $method) {
                    return date("Y-m-d", strtotime($order->order_date)) === $date
                        && strtoupper($order->payment_method) === $method;
                });

                $totalQuantity = 0;
                $totalPrice    = 0;

                foreach ($filtered as $order) {
                    foreach ($order->items as $item) {
                        $totalQuantity += $item['quantity'];
                        $totalPrice += $item['total'];
                    }
                }

                $data[$date][$method] = [
                    'payment_method' => $method,
                    'price'          => number_format($totalPrice, 2),
                    'quantity'       => $totalQuantity,
                ];
            }
        }

        return $data;
    }

    public function getOrders($from, $to)
    {
        $order_status = request('order_status');

        return Order::orderByDesc('id')
            ->when($order_status, function ($q) use ($order_status) {
                $q->where('order_status', $order_status);
            })
            // ->whereNot("order_status", Order::CANCELLED)
            // ->where("order_id","55524")
            ->whereBetween('order_date', [$from . " 00:00:00", $to . " 23:59:59"])
            ->withOut("customer", "payments")
            ->with("business_source")
            ->get(["id", "order_date", "order_id", "total", "channel", "payment_method", "items", "business_source_id"]);
    }
}
