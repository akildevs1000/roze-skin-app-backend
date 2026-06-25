<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StockLedger;
use App\Models\WpProductMap;

/**
 * Translates an order's WordPress line-items into inventory stock movements
 * when the order is converted to an invoice (and reverses them on cancel).
 *
 * Mapping lives in wp_product_maps / wp_product_map_items (see WpProductMap).
 * Actual stock writes go through StockService so the ledger stays the single
 * source of truth.
 */
class StockSyncService
{
    const SOURCE_TYPE = 'sales_invoice';

    public function __construct(private StockService $stock)
    {
    }

    /**
     * Deduct stock for every mapped line of the invoice's order.
     *
     * Idempotent: if this invoice has already produced SALE movements, nothing
     * happens. Lines whose product_id has no mapping are NOT deducted and are
     * returned so the caller can surface them.
     *
     * @return array{deducted:int, unmapped:array<int,array{product_id:string,name:?string,quantity:int}>}
     */
    public function deductForInvoice(Invoice $invoice): array
    {
        $order = $invoice->order;
        $items = $order ? (array) $order->items : [];

        $result = ['deducted' => 0, 'unmapped' => []];

        if (! $items || $this->alreadyDeducted($invoice)) {
            return $result;
        }

        // Pre-load the maps for every product_id in this order.
        $productIds = collect($items)
            ->pluck('product_id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (string) $id)
            ->unique();

        $maps = WpProductMap::with('items')
            ->whereIn('wp_product_id', $productIds)
            ->get()
            ->keyBy('wp_product_id');

        foreach ($items as $line) {
            $productId = isset($line['product_id']) ? (string) $line['product_id'] : '';
            $orderedQty = (int) ($line['quantity'] ?? 0);

            if ($productId === '' || $orderedQty <= 0) {
                continue;
            }

            /** @var WpProductMap|null $map */
            $map = $maps->get($productId);

            // No mapping at all -> report it, deduct nothing.
            if (! $map) {
                $result['unmapped'][] = [
                    'product_id' => $productId,
                    'name'       => $line['item'] ?? null,
                    'quantity'   => $orderedQty,
                ];
                continue;
            }

            // Intentionally stock-neutral (e.g. an "Any 3" bundle parent).
            if ($map->skip_stock) {
                continue;
            }

            foreach ($map->items as $component) {
                $this->stock->decrease(
                    $component->inventory_item_id,
                    $orderedQty * (int) $component->qty,
                    StockLedger::SALE,
                    StockLedger::BUCKET_SELLABLE,
                    [
                        'source_type'    => self::SOURCE_TYPE,
                        'source_id'      => $invoice->id,
                        'reference'      => $order->reference_id ?? null,
                        'customer_name'  => optional($order->customer)->full_name,
                        // The sale already happened in the real world; never block
                        // invoicing because our on-hand count is behind.
                        'allow_negative' => true,
                    ]
                );
                $result['deducted']++;
            }
        }

        return $result;
    }

    /**
     * Add stock back for an invoice that is being cancelled. Idempotent.
     *
     * @return int number of reversal movements written
     */
    public function reverseForInvoice(Invoice $invoice): int
    {
        if (! $this->alreadyDeducted($invoice) || $this->alreadyReversed($invoice)) {
            return 0;
        }

        $sales = StockLedger::where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $invoice->id)
            ->where('movement_type', StockLedger::SALE)
            ->get();

        $count = 0;
        foreach ($sales as $sale) {
            $this->stock->increase(
                $sale->product_id,
                abs((int) $sale->quantity),
                StockLedger::SALES_INVOICE_CANCEL,
                $sale->bucket,
                [
                    'source_type'    => self::SOURCE_TYPE,
                    'source_id'      => $invoice->id,
                    'reference'      => $sale->reference,
                    'allow_negative' => true,
                ]
            );
            $count++;
        }

        return $count;
    }

    private function alreadyDeducted(Invoice $invoice): bool
    {
        return StockLedger::where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $invoice->id)
            ->where('movement_type', StockLedger::SALE)
            ->exists();
    }

    private function alreadyReversed(Invoice $invoice): bool
    {
        return StockLedger::where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $invoice->id)
            ->where('movement_type', StockLedger::SALES_INVOICE_CANCEL)
            ->exists();
    }
}
