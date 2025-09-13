<?php
namespace App\Http\Controllers;

use App\Models\Order;

class CityController extends Controller
{
    public function dropDown()
    {
        $allItem = ["id" => null, "name" => "Cirty"];

        $locations = [
            ["value" => null, "label" => "Select All"],
            ["value" => "AUH", "label" => "Abu Dhabi"],
            ["value" => "AJM", "label" => "Ajman"],
            ["value" => "ALN", "label" => "Al Ain"],
            ["value" => "DXB", "label" => "Dubai"],
            ["value" => "FUJ", "label" => "Fujairah"],
            ["value" => "DXJ", "label" => "Jebel Ali"],
            ["value" => "RAK", "label" => "Ras Al Khaimah"],
            ["value" => "SHJ", "label" => "Sharjah"],
            ["value" => "UAQ", "label" => "Umm Al Quwain"],
        ];

    }

    public function report()
    {
        $city = request('city');
        $from = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to   = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        return Order::query()
            ->whereBetween('order_date', [$from, $to])
            ->whereHas('customer.shipping_address', function ($q) use ($city) {
                if ($city && $city !== "Select All") {
                    $q->where('city', $city);
                }
            })
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->join('shipping_addresses', 'customers.id', '=', 'shipping_addresses.customer_id')
            ->when($city && $city !== "Select All", function ($q) use ($city) {
                $q->where('shipping_addresses.city', $city);
            })
            ->groupBy('shipping_addresses.city')
            ->selectRaw('shipping_addresses.city, COUNT(orders.id) as orders_count, SUM(orders.total) as orders_sum_total')
            ->get();
    }

}
