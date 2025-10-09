<?php
namespace App\Http\Controllers;

use App\Models\OrderItem;

class OrderItemController extends Controller
{
    public function dropDown()
    {
        return OrderItem::orderByDesc('id')->get();
    }
    
    public function index()
    {
        $search = trim(request('search'));
        $status = trim(request('status'));

        if (request('search') && ! is_numeric($search)) {
            return;
        }

        $from = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to   = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");

        $dates = [$from, $to];

        $perPage = request('per_page', 15); // Limit max results per page

        return OrderItem::orderByDesc('id')
            ->when($search, function ($q) use ($search) {
                $q->where('order_id', $search);
            })

            ->when($status, function ($q){
                $q->whereDoesntHave("product");
            })

           
      
            ->whereBetween('order_date', $dates)

            ->with(['product'])

            ->paginate($perPage);
    }
}
