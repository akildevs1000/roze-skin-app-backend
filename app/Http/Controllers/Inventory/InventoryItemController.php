<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        return InventoryItem::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%")
                    ->orWhere('sku', 'LIKE', "%{$request->search}%");
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Per-item history: current balances + counts by movement type, plus the
     * recent ledger entries. Used by the row drill-down on the list.
     *
     * Optional `from`/`to` (YYYY-MM-DD) scope the movement aggregates and the
     * ledger to a date range so the summary cards match the History table for
     * the selected period. Current balances (available/non_sellable) are always
     * a live snapshot and are never date-filtered.
     */
    public function history(InventoryItem $inventoryItem, Request $request)
    {
        $id    = $inventoryItem->id;
        $stock = InventoryStock::where('product_id', $id)->first();

        $from = $request->filled('from') ? $request->from : null;
        $to   = $request->filled('to') ? $request->to : null;

        $dateScope = function ($query) use ($from, $to) {
            if ($from) $query->whereDate('created_at', '>=', $from);
            if ($to)   $query->whereDate('created_at', '<=', $to);
            return $query;
        };

        $sum = function (array $types, $abs = false) use ($id, $dateScope) {
            $expr = $abs ? 'COALESCE(SUM(ABS(quantity)),0)' : 'COALESCE(SUM(quantity),0)';
            return (int) $dateScope(
                StockLedger::where('product_id', $id)->whereIn('movement_type', $types)
            )->selectRaw("$expr as t")->value('t');
        };

        $sellable    = $stock ? (int) $stock->sellable_qty : 0;
        $nonSellable = $stock ? (int) $stock->non_sellable_qty : 0;

        // Most recent goods receipt for this item — drives the "Last Purchase" card.
        $lastReceiptItem = GoodsReceiptItem::with('goods_receipt.purchase_order')
            ->where('product_id', $id)
            ->orderByDesc('id')
            ->first();

        $lastPurchase = null;
        if ($lastReceiptItem && $lastReceiptItem->goods_receipt) {
            $gr = $lastReceiptItem->goods_receipt;
            $lastPurchase = [
                'po_number'     => optional($gr->purchase_order)->po_number,
                'grn'           => $gr->reference_id,
                'received_date' => $gr->received_date,
                'qty'           => (int) $lastReceiptItem->qty_received,
            ];
        }

        return [
            'item'    => $inventoryItem,
            'summary' => [
                'available'    => $sellable,
                'non_sellable' => $nonSellable,
                'total'        => $sellable + $nonSellable,
                'received'     => $sum([StockLedger::GRN_RECEIVE]),
                'sold'         => $sum([StockLedger::SALE], true),
                'returned'     => $sum([StockLedger::CUSTOMER_RETURN, StockLedger::RTO]),
                'cancelled'    => $sum([StockLedger::SALES_INVOICE_CANCEL, StockLedger::SHIPMENT_CANCEL]),
                'adjusted_in'  => $sum([StockLedger::ADJUSTMENT_INCREASE]),
                'adjusted_out' => $sum([StockLedger::ADJUSTMENT_DECREASE], true),
            ],
            'last_purchase' => $lastPurchase,
            'ledger'  => $dateScope(
                StockLedger::where('product_id', $id)
                    ->whereNotIn('movement_type', [StockLedger::ADJUSTMENT_INCREASE, StockLedger::ADJUSTMENT_DECREASE])
            )->orderByDesc('id')->limit(200)->get(),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImage($request);
        }

        return InventoryItem::create($data);
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $data = $this->validated($request, $inventoryItem->id);

        if ($request->hasFile('image')) {
            // Remove the previous image before saving the new one.
            if ($inventoryItem->image && File::exists(public_path($inventoryItem->image))) {
                File::delete(public_path($inventoryItem->image));
            }
            $data['image'] = $this->storeImage($request);
        }

        $inventoryItem->update($data);

        return $inventoryItem->fresh();
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        // Block deletion once the item has any stock movement history.
        if (StockLedger::where('product_id', $inventoryItem->id)->exists()) {
            return response()->json(['message' => 'This item has stock history and cannot be deleted. Mark it inactive instead.'], 422);
        }

        if ($inventoryItem->image && File::exists(public_path($inventoryItem->image))) {
            File::delete(public_path($inventoryItem->image));
        }

        InventoryStock::where('product_id', $inventoryItem->id)->delete();
        $inventoryItem->delete();

        return response()->noContent();
    }

    private function validated(Request $request, $ignoreId = null): array
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'sku'         => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'unit_cost'   => 'nullable|numeric|min:0',
            'status'      => 'nullable|in:active,inactive',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        unset($validated['image']); // file is handled separately, not mass-assigned

        return $validated + ['status' => $request->input('status', 'active')];
    }

    private function storeImage(Request $request): string
    {
        $image    = $request->file('image');
        $filename = time() . '_' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('inventory/items'), $filename);

        return 'inventory/items/' . $filename;
    }
}
