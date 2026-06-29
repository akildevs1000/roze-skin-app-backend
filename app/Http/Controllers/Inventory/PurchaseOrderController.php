<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderAttachment;
use App\Models\GoodsReceipt;
use App\Models\StockLedger;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PurchaseOrderController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    /** Lightweight list for selects (e.g. choosing a PO to receive against). */
    public function dropDown()
    {
        return PurchaseOrder::with('items.product', 'vendor', 'warehouse', 'customer')
            ->whereIn('status', ['pending', 'partially_received'])
            ->orderByDesc('id')
            ->get();
    }

    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        return PurchaseOrder::with('items.product', 'vendor', 'warehouse', 'customer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('id', ltrim($request->search, 'PO-0'))
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'LIKE', "%{$request->search}%"));
            })
            ->when($request->filled('from') && $request->filled('to'), function ($q) use ($request) {
                $q->whereBetween('order_date', [$request->from, $request->to]);
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return $purchaseOrder->load('items.product', 'vendor', 'warehouse', 'customer', 'attachments');
    }

    /** Next PO number, for previewing on the create form. */
    public function nextNumber()
    {
        return ['number' => PurchaseOrder::nextNumber()];
    }

    /**
     * Creating a PO never changes stock (rule #1).
     */
    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        // "Save as Draft" stores a draft; "Save and Send" (default) makes it pending.
        $status = ($data['status'] ?? null) === 'draft' ? 'draft' : 'pending';

        return DB::transaction(function () use ($data, $status) {
            $po = PurchaseOrder::create($this->header($data) + [
                'status'    => $status,
                'po_number' => PurchaseOrder::nextNumber(),
                'user_id'   => optional(auth('sanctum')->user())->id,
            ]);

            // Auto-generate the reference number (aligned to the PO number) when none is supplied.
            if (empty($po->reference)) {
                $po->update(['reference' => str_replace('PO-', 'REF-', $po->po_number)]);
            }

            $this->syncItems($po, $data['items'], $data['tax_mode'] ?? 'exclusive');

            return $po->load('items.product', 'vendor', 'warehouse', 'customer', 'attachments');
        });
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status === 'cancelled') {
            return response()->json(['message' => 'A cancelled purchase order cannot be edited.'], 422);
        }

        $data = $this->validatePayload($request);

        // Editing replaces all lines, so any goods already received against this PO
        // must have their stock reversed first — otherwise on-hand would stay inflated.
        $hadReceipts = $purchaseOrder->received_qty > 0;

        // Allow a draft to be promoted to pending (Save and Send); never downgrade.
        $header = $this->header($data);
        if (($data['status'] ?? null) === 'pending' && $purchaseOrder->status === 'draft') {
            $header['status'] = 'pending';
        }
        // A previously (partially) received PO goes back to a clean pending state after the rewrite.
        if ($hadReceipts) {
            $header['status'] = 'pending';
        }

        return DB::transaction(function () use ($data, $purchaseOrder, $header, $hadReceipts) {
            if ($hadReceipts) {
                $this->reverseReceipts($purchaseOrder);
            }

            $purchaseOrder->update($header);

            // Replace lines (received quantities were reversed above, so they reset to 0).
            $purchaseOrder->items()->delete();
            $this->syncItems($purchaseOrder, $data['items'], $data['tax_mode'] ?? 'exclusive');

            return $purchaseOrder->load('items.product', 'vendor', 'warehouse', 'customer', 'attachments');
        });
    }

    /**
     * Reverse every goods receipt booked against this PO: write a compensating
     * stock-out ledger entry for each received line, then remove the receipts.
     * Allowed to drive stock negative (e.g. goods already sold) so the rewrite
     * always succeeds — the negative balance then reflects the real shortfall.
     */
    private function reverseReceipts(PurchaseOrder $purchaseOrder): void
    {
        $receipts = GoodsReceipt::with('items')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->get();

        foreach ($receipts as $grn) {
            foreach ($grn->items as $item) {
                $this->stock->decrease(
                    $item->product_id,
                    $item->qty_received,
                    StockLedger::GRN_REVERSAL,
                    StockLedger::BUCKET_SELLABLE,
                    [
                        'source_type'    => 'goods_receipt',
                        'source_id'      => $grn->id,
                        'reference'      => $grn->reference_id,
                        'reason'         => 'Purchase order ' . $purchaseOrder->reference_id . ' edited/deleted',
                        'allow_negative' => true,
                    ]
                );
            }

            $grn->items()->delete();
            $grn->delete();
        }
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'vendor_id'           => 'required|integer|exists:vendors,id',
            'delivery_type'       => 'nullable|in:warehouse,customer',
            'warehouse_id'        => 'nullable|integer|exists:warehouses,id',
            'customer_id'         => 'nullable|integer|exists:customers,id',
            'reference'           => 'nullable|string|max:255',
            'order_date'          => 'required|date',
            'expected_date'       => 'nullable|date',
            'payment_terms'       => 'nullable|string|max:100',
            'shipment_preference' => 'nullable|string|max:255',
            'tax_mode'            => 'nullable|in:exclusive,inclusive',
            'discount_level'      => 'nullable|in:transaction,line',
            'discount'            => 'nullable|numeric|min:0',
            'discount_type'       => 'nullable|in:percentage,amount',
            'status'              => 'nullable|in:draft,pending',
            'notes'               => 'nullable|string',
            'terms'               => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|integer|exists:inventory_items,id',
            'items.*.qty_ordered' => 'required|integer|min:1',
            'items.*.unit_cost'   => 'required|numeric|min:0',
            'items.*.tax_name'    => 'nullable|string|max:60',
            'items.*.tax_rate'    => 'nullable|numeric|min:0|max:100',
        ]);
    }

    /**
     * Split a line into its net amount and tax amount, honouring the
     * tax mode (exclusive adds tax on top; inclusive backs it out of the rate).
     */
    private function lineAmounts(array $item, string $taxMode): array
    {
        $gross = (float) $item['qty_ordered'] * (float) $item['unit_cost'];
        $rate  = (float) ($item['tax_rate'] ?? 0);

        if ($rate <= 0) {
            return ['net' => round($gross, 2), 'tax' => 0.0];
        }

        if ($taxMode === 'inclusive') {
            $net = round($gross / (1 + $rate / 100), 2);
            return ['net' => $net, 'tax' => round($gross - $net, 2)];
        }

        return ['net' => round($gross, 2), 'tax' => round($gross * $rate / 100, 2)];
    }

    /** Build the header (with computed sub_total/tax/total) from validated data. */
    private function header(array $data): array
    {
        $taxMode  = $data['tax_mode'] ?? 'exclusive';
        $subTotal = 0.0;
        $taxTotal = 0.0;
        foreach ($data['items'] as $item) {
            $a = $this->lineAmounts($item, $taxMode);
            $subTotal += $a['net'];
            $taxTotal += $a['tax'];
        }

        $discount = (float) ($data['discount'] ?? 0);
        $type     = $data['discount_type'] ?? 'percentage';
        $discountAmount = $type === 'amount' ? $discount : round($subTotal * $discount / 100, 2);
        $total = max(0, round($subTotal - $discountAmount + $taxTotal, 2));

        return [
            'vendor_id'           => $data['vendor_id'],
            'delivery_type'       => $data['delivery_type'] ?? 'warehouse',
            'warehouse_id'        => $data['warehouse_id'] ?? null,
            'customer_id'         => $data['customer_id'] ?? null,
            'reference'           => $data['reference'] ?? null,
            'order_date'          => $data['order_date'],
            'expected_date'       => $data['expected_date'] ?? null,
            'payment_terms'       => $data['payment_terms'] ?? null,
            'shipment_preference' => $data['shipment_preference'] ?? null,
            'notes'               => $data['notes'] ?? null,
            'terms'               => $data['terms'] ?? null,
            'tax_mode'            => $taxMode,
            'discount_level'      => $data['discount_level'] ?? 'transaction',
            'discount'            => $discount,
            'discount_type'       => $type,
            'sub_total'           => round($subTotal, 2),
            'tax_total'           => round($taxTotal, 2),
            'total'               => $total,
        ];
    }

    private function syncItems(PurchaseOrder $po, array $items, string $taxMode): void
    {
        foreach ($items as $item) {
            $a = $this->lineAmounts($item, $taxMode);
            $po->items()->create([
                'product_id'   => $item['product_id'],
                'qty_ordered'  => $item['qty_ordered'],
                'qty_received' => 0,
                'unit_cost'    => $item['unit_cost'],
                'tax_name'     => $item['tax_name'] ?? null,
                'tax_rate'     => $item['tax_rate'] ?? 0,
                'tax_amount'   => $a['tax'],
            ]);
        }
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status === 'received') {
            return response()->json(['message' => 'A fully received purchase order cannot be cancelled.'], 422);
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        return $purchaseOrder;
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        return DB::transaction(function () use ($purchaseOrder) {
            // Reverse the stock from any goods received against this PO, then drop the receipts.
            if ($purchaseOrder->received_qty > 0) {
                $this->reverseReceipts($purchaseOrder);
            }

            // Remove attachment files + records so nothing is orphaned.
            foreach ($purchaseOrder->attachments as $attachment) {
                if ($attachment->path && File::exists(public_path($attachment->path))) {
                    File::delete(public_path($attachment->path));
                }
            }
            $purchaseOrder->attachments()->delete();

            $purchaseOrder->items()->delete();
            $purchaseOrder->delete();

            return response()->noContent();
        });
    }

    /** Pending (ordered but not yet received) quantities — feeds rule #13 "purchase pending". */
    public function report(Request $request)
    {
        $rows = PurchaseOrderItem::with('product')
            ->whereHas('purchase_order', fn ($q) => $q->whereNotIn('status', ['cancelled', 'received']))
            ->get()
            ->filter(fn ($i) => $i->pending_qty > 0)
            ->map(fn ($i) => [
                'product_id'   => $i->product_id,
                'sku'          => optional($i->product)->sku,
                'product_name' => optional($i->product)->name,
                'qty_ordered'  => $i->qty_ordered,
                'qty_received' => $i->qty_received,
                'pending_qty'  => $i->pending_qty,
                'unit_cost'    => $i->unit_cost,
            ])
            ->values();

        return $rows;
    }

    /** Attach one or more files to a purchase order (max 10 files, 10MB each). */
    public function storeAttachments(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'files'   => 'required|array|max:10',
            'files.*' => 'file|max:10240', // 10 MB
        ]);

        $dir = public_path('purchase_orders');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $created = [];
        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $size = $file->getSize();
            $filename = time() . '_' . uniqid() . '_' . preg_replace('/\s+/', '_', $originalName);
            $file->move($dir, $filename);

            $created[] = $purchaseOrder->attachments()->create([
                'path'          => 'purchase_orders/' . $filename,
                'original_name' => $originalName,
                'size'          => $size,
            ]);
        }

        return response()->json($created, 201);
    }

    public function destroyAttachment(PurchaseOrderAttachment $attachment)
    {
        if ($attachment->path && File::exists(public_path($attachment->path))) {
            File::delete(public_path($attachment->path));
        }

        $attachment->delete();

        return response()->noContent();
    }
}
