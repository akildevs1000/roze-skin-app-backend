<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLedger;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsReceiptController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        return GoodsReceipt::with(['items.product', 'purchase_order', 'vendor'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('id', ltrim($request->search, 'GRN-0'))
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'LIKE', "%{$request->search}%"));
            })
            ->when($request->filled('from') && $request->filled('to'), function ($q) use ($request) {
                $q->whereBetween('received_date', [$request->from, $request->to]);
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function show(GoodsReceipt $goodsReceipt)
    {
        return $goodsReceipt->load(['items.product', 'purchase_order', 'vendor']);
    }

    /**
     * Record a goods receipt. Stock increases here and only here (rule #2).
     * Partial receiving is supported — each line's qty_received accumulates on the PO (rule #3).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id'           => 'nullable|integer|exists:purchase_orders,id',
            'vendor_id'                   => 'nullable|integer|exists:vendors,id',
            'received_date'               => 'required|date',
            'notes'                       => 'nullable|string',
            'items'                       => 'required|array|min:1',
            'items.*.product_id'          => 'required|integer',
            'items.*.purchase_order_item_id' => 'nullable|integer',
            'items.*.qty_received'        => 'required|integer|min:1',
            'items.*.unit_cost'           => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($data) {
            // If receiving against a PO, inherit its vendor when none was passed.
            $vendorId = $data['vendor_id'] ?? null;
            if (! $vendorId && ! empty($data['purchase_order_id'])) {
                $vendorId = optional(PurchaseOrder::find($data['purchase_order_id']))->vendor_id;
            }

            $grn = GoodsReceipt::create([
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'vendor_id'         => $vendorId,
                'received_date'     => $data['received_date'],
                'notes'             => $data['notes'] ?? null,
                'status'            => 'received',
                'user_id'           => optional(auth('sanctum')->user())->id,
            ]);

            foreach ($data['items'] as $item) {
                $grn->items()->create([
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'product_id'             => $item['product_id'],
                    'qty_received'           => $item['qty_received'],
                    'unit_cost'              => $item['unit_cost'] ?? 0,
                ]);

                // Increase stock + write ledger.
                $this->stock->increase(
                    $item['product_id'],
                    $item['qty_received'],
                    StockLedger::GRN_RECEIVE,
                    StockLedger::BUCKET_SELLABLE,
                    [
                        'source_type' => 'goods_receipt',
                        'source_id'   => $grn->id,
                        'reference'   => $grn->reference_id,
                    ]
                );

                // Keep the item's standard cost current from the latest receipt (for valuation).
                if (! empty($item['unit_cost'])) {
                    \App\Models\InventoryItem::where('id', $item['product_id'])->update(['unit_cost' => $item['unit_cost']]);
                }

                // Roll the received qty up onto the matching PO line, if any.
                if (! empty($item['purchase_order_item_id'])) {
                    $poItem = PurchaseOrderItem::find($item['purchase_order_item_id']);
                    if ($poItem) {
                        $poItem->increment('qty_received', $item['qty_received']);
                    }
                }
            }

            // Recompute the PO status (pending / partially_received / received).
            if (! empty($data['purchase_order_id'])) {
                $this->syncPurchaseOrderStatus($data['purchase_order_id']);
            }

            return $grn->load(['items.product', 'purchase_order', 'vendor']);
        });
    }

    private function syncPurchaseOrderStatus($purchaseOrderId)
    {
        $po = PurchaseOrder::with('items')->find($purchaseOrderId);
        if (! $po) {
            return;
        }

        $ordered  = $po->items->sum('qty_ordered');
        $received = $po->items->sum('qty_received');

        $status = $received <= 0
            ? 'pending'
            : ($received >= $ordered ? 'received' : 'partially_received');

        $po->update(['status' => $status]);
    }
}
