<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\PurchaseOrderItem;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryDashboardController extends Controller
{
    /**
     * Dashboard metrics (rule #13): current stock, low-stock alerts, purchase pending,
     * received / sold / returned / damaged stock, and total stock value.
     */
    public function index(Request $request)
    {
        // Optional date window for the flow metrics (received/sold/returned).
        $from = $request->filled('from') ? $request->from . ' 00:00:00' : null;
        $to   = $request->filled('to') ? $request->to . ' 23:59:59' : null;

        $current = InventoryStock::query()
            ->selectRaw('COALESCE(SUM(sellable_qty),0) as sellable, COALESCE(SUM(non_sellable_qty),0) as non_sellable')
            ->first();

        // Stock value = on-hand units * item unit cost.
        $stockValue = DB::table('inventory_stocks as s')
            ->join('inventory_items as i', 'i.id', '=', 's.product_id')
            ->selectRaw('COALESCE(SUM((s.sellable_qty + s.non_sellable_qty) * i.unit_cost),0) as value')
            ->value('value');

        $lowStockCount = InventoryStock::whereColumn('sellable_qty', '<=', 'reorder_level')
            ->where('reorder_level', '>', 0)
            ->count();

        // Purchase pending = ordered but not yet received, across active POs.
        $purchasePending = PurchaseOrderItem::whereHas('purchase_order', function ($q) {
            $q->whereNotIn('status', ['cancelled', 'received']);
        })->selectRaw('COALESCE(SUM(qty_ordered - qty_received),0) as pending')->value('pending');

        $ledgerSum = function (array $types, $abs = false) use ($from, $to) {
            $expr = $abs ? 'COALESCE(SUM(ABS(quantity)),0)' : 'COALESCE(SUM(quantity),0)';

            return (int) StockLedger::whereIn('movement_type', $types)
                ->when($from && $to, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
                ->selectRaw("$expr as total")
                ->value('total');
        };

        return [
            'current_stock'    => (int) $current->sellable + (int) $current->non_sellable,
            'sellable_stock'   => (int) $current->sellable,
            'non_sellable_stock' => (int) $current->non_sellable,
            'damaged_stock'    => (int) $current->non_sellable, // damaged + expired live in non-sellable
            'low_stock_alerts' => $lowStockCount,
            'purchase_pending' => (int) $purchasePending,
            'received_stock'   => $ledgerSum([StockLedger::GRN_RECEIVE]),
            'sold_stock'       => $ledgerSum([StockLedger::SALE], true),
            'returned_stock'   => $ledgerSum([StockLedger::CUSTOMER_RETURN, StockLedger::RTO]),
            'stock_value'      => round((float) $stockValue, 2),
        ];
    }

    /** Recent movements for a dashboard activity feed. */
    public function recentMovements()
    {
        return StockLedger::with('product:id,name,sku')
            ->orderByDesc('id')
            ->limit(15)
            ->get();
    }
}
