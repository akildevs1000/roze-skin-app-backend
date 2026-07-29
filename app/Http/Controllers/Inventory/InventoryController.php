<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\StockLedger;
use App\Services\StockService;
use App\Services\StockSyncService;
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
        // Most recent "opening stock" adjustment per product, if any — filtered on
        // both movement_type AND reference='OPENING' so a future generic manual
        // stock-correction feature (which would likely reuse the same adjustment
        // movement types) can never be mistaken for an opening-stock date.
        $openingDates = StockLedger::selectRaw('product_id, MAX(created_at) as opening_date')
            ->where('reference', 'OPENING')
            ->whereIn('movement_type', [StockLedger::ADJUSTMENT_INCREASE, StockLedger::ADJUSTMENT_DECREASE])
            ->groupBy('product_id');

        return InventoryItem::query()
            ->where('inventory_items.status', 'active')
            ->leftJoin('inventory_stocks as s', 's.product_id', '=', 'inventory_items.id')
            ->leftJoinSub($openingDates, 'ol', 'ol.product_id', '=', 'inventory_items.id')
            ->select(
                'inventory_items.id',
                'inventory_items.name',
                'inventory_items.sku',
                'inventory_items.image',
                'inventory_items.unit_cost',
                DB::raw('COALESCE(s.sellable_qty, 0) as sellable_qty'),
                DB::raw('COALESCE(s.non_sellable_qty, 0) as non_sellable_qty'),
                'ol.opening_date'
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

    /**
     * Set opening / initial stock directly, without a purchase order. For each
     * item the given quantity becomes the absolute sellable balance: the
     * difference from the current balance is posted as an adjustment so the
     * ledger stays the single source of truth. Intended as a one-time setup tool.
     */
    public function openingStock(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:inventory_items,id',
            'items.*.quantity'   => 'required|integer|min:0',
        ]);

        $updated = 0;

        DB::transaction(function () use ($data, $stock, &$updated) {
            foreach ($data['items'] as $row) {
                $productId = (int) $row['product_id'];
                $target    = (int) $row['quantity'];

                $current = (int) optional(
                    InventoryStock::where('product_id', $productId)->first()
                )->sellable_qty;

                $delta = $target - $current;

                if ($delta === 0) {
                    continue;
                }

                $stock->move(
                    $productId,
                    $delta,
                    $delta > 0 ? StockLedger::ADJUSTMENT_INCREASE : StockLedger::ADJUSTMENT_DECREASE,
                    StockLedger::BUCKET_SELLABLE,
                    [
                        'reference'      => 'OPENING',
                        'reason'         => 'Opening stock',
                        'allow_negative' => true,
                    ]
                );

                $updated++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Opening stock saved for {$updated} item(s).",
            'updated' => $updated,
        ]);
    }

    /** Read the master stock-linking switch. */
    public function stockSyncStatus()
    {
        return [
            'enabled' => (bool) ((int) Setting::get(StockSyncService::STOCK_SYNC_SETTING, '0')),
        ];
    }

    /** Turn the master stock-linking switch on/off. */
    public function setStockSync(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);

        Setting::put(StockSyncService::STOCK_SYNC_SETTING, $data['enabled'] ? '1' : '0');

        return ['enabled' => (bool) $data['enabled']];
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
