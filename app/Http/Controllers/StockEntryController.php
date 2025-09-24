<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StockEntry;
use Illuminate\Http\Request;

class StockEntryController extends Controller
{
    public function dropDown()
    {
        return StockEntry::get();
    }

    public function index()
    {
        $inventory_item_id = request('inventory_item_id');
        $from              = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to                = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");
        $dates             = [$from, $to];

        return StockEntry::query()
            ->with("inventory_item")
            ->when($inventory_item_id,  fn($q, $id) => $q->where('inventory_item_id', $id))
            ->whereBetween('stock_date', $dates)
            ->paginate(request("per_page"));
    }

    public function store(Request $request)
    {
        // Validate all required fields
        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'stock_date'        => 'required|date',
            'name'              => 'required|string|min:5|max:255',
            'qty_added'         => 'required|integer|min:0',
        ]);

        $validated["qty_available"] = $validated["qty_added"];

        // Create the stock entry
        return StockEntry::create($validated);
    }

    public function update(Request $request, $id)
    {
        $stockEntry = StockEntry::findOrFail($id);

        $validated = $request->validate([
            'inventory_item_id' => [
                'required',
                'exists:inventory_items,id',

            ],
            'stock_date'        => 'required|date',
            'name'              => 'required|string|min:5|max:255',
            'qty_added'         => 'required|integer|min:0',
            // Rule::unique('stock_entries')->ignore($id),
        ]);

        $validated["qty_available"] = $validated["qty_added"];

        $stockEntry->update($validated);

        return $stockEntry->fresh();
    }

    public function destroy(StockEntry $StockEntry)
    {
        $StockEntry->delete();

        return response()->noContent();
    }

    public function report()
    {
        $inventory_id = request('inventory_id');
        $from         = request('from') ? request('from') . " 00:00:00" : date("Y-m-d 00:00:00");
        $to           = request('to') ? request('to') . " 23:59:59" : date("Y-m-d 23:59:59");
        $dates        = [$from, $to];

        $orderIds = Order::whereBetween("order_date", $dates)->whereNotIn("order_status", ["cancelled"])->pluck("order_id");

        return StockEntry::query()
            ->when($inventory_id, function ($query, $id) {
                $query->whereHas("order_items", fn($q) => $q->where('inventory_id', $id));
            })
            ->whereHas("order_items", function ($q) use ($orderIds) {
                $q->whereIn('order_id', $orderIds);
            })
            ->withCount([
                'order_items as orders_count' => function ($q) use ($inventory_id, $dates) {
                    $q->whereBetween('order_date', $dates);
                    if ($inventory_id) {
                        $q->where('inventory_id', $inventory_id);
                    }
                },
            ])
            ->withSum([
                'order_items as orders_sum_total' => function ($q) use ($inventory_id, $dates) {
                    $q->whereBetween('order_date', $dates);
                    if ($inventory_id) {
                        $q->where('inventory_id', $inventory_id);
                    }
                },
            ], 'rate')
            ->get();
    }
}
