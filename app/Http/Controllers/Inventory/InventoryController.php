<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Inventory list — every inventory item with its current sellable / non-sellable
     * balances and stock value. Built from inventory_items (left joined to
     * inventory_stocks) so that items without any movement yet still appear with zero stock.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = $this->baseQuery($request);

        if ($request->boolean('low_stock')) {
            $query->whereColumn('s.sellable_qty', '<=', 's.reorder_level')
                ->where('s.reorder_level', '>', 0);
        }

        return $query->orderBy('i.name')->paginate($perPage);
    }

    /** Inventory items + stock for selects. */
    public function dropDown()
    {
        return InventoryItem::query()
            ->where('inventory_items.status', 'active')
            ->leftJoin('inventory_stocks as s', 's.product_id', '=', 'inventory_items.id')
            ->select(
                'inventory_items.id',
                'inventory_items.name',
                'inventory_items.sku',
                'inventory_items.image',
                'inventory_items.unit_cost',
                DB::raw('COALESCE(s.sellable_qty, 0) as sellable_qty'),
                DB::raw('COALESCE(s.non_sellable_qty, 0) as non_sellable_qty')
            )
            ->orderBy('inventory_items.name')
            ->get();
    }

    /** Low stock alerts (rule #13). */
    public function lowStock(Request $request)
    {
        return $this->baseQuery($request)
            ->whereColumn('s.sellable_qty', '<=', 's.reorder_level')
            ->where('s.reorder_level', '>', 0)
            ->orderBy('s.sellable_qty')
            ->get();
    }

    /** Set / update the low-stock threshold for an inventory item. */
    public function setReorderLevel(Request $request)
    {
        $data = $request->validate([
            'product_id'    => 'required|integer|exists:inventory_items,id',
            'reorder_level' => 'required|integer|min:0',
        ]);

        $stock = InventoryStock::firstOrCreate(['product_id' => $data['product_id']]);
        $stock->update(['reorder_level' => $data['reorder_level']]);

        return $stock;
    }

    private function baseQuery(Request $request)
    {
        return InventoryItem::query()
            ->from('inventory_items as i')
            ->leftJoin('inventory_stocks as s', 's.product_id', '=', 'i.id')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($w) use ($request) {
                    $w->where('i.name', 'LIKE', "%{$request->search}%")
                        ->orWhere('i.sku', 'LIKE', "%{$request->search}%");
                });
            })
            ->select(
                'i.id',
                'i.id as product_id',
                'i.name',
                'i.sku',
                'i.image',
                'i.unit_cost',
                DB::raw('COALESCE(s.sellable_qty, 0) as sellable_qty'),
                DB::raw('COALESCE(s.non_sellable_qty, 0) as non_sellable_qty'),
                DB::raw('COALESCE(s.sellable_qty, 0) + COALESCE(s.non_sellable_qty, 0) as total_qty'),
                DB::raw('COALESCE(s.reorder_level, 0) as reorder_level'),
                DB::raw('(COALESCE(s.sellable_qty, 0) + COALESCE(s.non_sellable_qty, 0)) * i.unit_cost as stock_value')
            );
    }
}
