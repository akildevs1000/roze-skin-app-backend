<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Setting;
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

    /** Settings key for the master on/off switch for inventory stock linking. */
    const STOCK_SYNC_SETTING = 'inventory_stock_sync_enabled';

    public function __construct(private StockService $stock)
    {
    }

    /**
     * Master switch. When disabled (the default), invoices/cancels/returns never
     * touch stock — so a fresh deployment is stock-neutral until it is turned on
     * from the Opening Stock screen.
     */
    public function enabled(): bool
    {
        return (bool) ((int) Setting::get(self::STOCK_SYNC_SETTING, '0'));
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
        $result = ['deducted' => 0, 'unmapped' => []];

        if (! $this->enabled()) {
            return $result;
        }

        $order = $invoice->order;
        $items = $order ? (array) $order->items : [];

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

        // What the customer picked inside any pick-your-own bundle on this
        // order, resolved to inventory components. See resolveBundleChoices().
        $chosen = $this->resolveBundleChoices($items);

        foreach ($items as $line) {
            $productId = isset($line['product_id']) ? (string) $line['product_id'] : '';
            $orderedQty = (int) ($line['quantity'] ?? 0);

            if ($productId === '' || $orderedQty <= 0) {
                continue;
            }

            /*
             * A pick-your-own bundle (Any 3, Any 4) typed into the app arrives
             * as ONE line, with the chosen products recorded only in
             * bundle_note - they never become order lines of their own the way
             * they do on a website order. The bundle parent itself is either
             * unmapped (Any 4) or marked skip_stock (Any 3), so until now
             * these orders deducted nothing whatsoever: 63 invoices and 234
             * units left the warehouse without ever coming off the system.
             *
             * When a line carries choices, they are what actually ships, so
             * they are what gets deducted - and the parent's own mapping is
             * skipped so nothing can be counted twice.
             */
            $picks = $this->bundleChoices($line);
            if ($picks) {
                foreach ($picks as $pick) {
                    $components = $chosen[$pick['name']] ?? null;

                    if (! $components) {
                        $result['unmapped'][] = [
                            'product_id' => $productId . ' (chosen: ' . $pick['name'] . ')',
                            'name'       => $pick['name'],
                            'quantity'   => $pick['qty'] * $orderedQty,
                        ];
                        continue;
                    }

                    foreach ($components as $inventoryItemId => $perUnit) {
                        $this->stock->decrease(
                            $inventoryItemId,
                            $orderedQty * $pick['qty'] * $perUnit,
                            StockLedger::SALE,
                            StockLedger::BUCKET_SELLABLE,
                            [
                                'source_type'    => self::SOURCE_TYPE,
                                'source_id'      => $invoice->id,
                                'reference'      => $invoice->reference_id ?? null,
                                'customer_name'  => optional($order->customer)->full_name,
                                'allow_negative' => true,
                            ]
                        );
                        $result['deducted']++;
                    }
                }

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
                        'reference'      => $invoice->reference_id ?? null,
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
     * The products chosen inside one pick-your-own bundle line.
     *
     * bundle_note is normally a JSON [{name, qty}, ...]. Orders placed before
     * that format existed carry a flat comma-separated list of short labels
     * instead; those cannot be resolved to catalog products reliably, so they
     * are ignored here and the line falls through to its own mapping (which
     * reports it as unmapped) rather than being silently half-deducted.
     *
     * @return array<int,array{name:string,qty:int}>
     */
    private function bundleChoices(array $line): array
    {
        $note = $line['bundle_note'] ?? null;

        if (! is_string($note) || trim($note) === '' || ! str_starts_with(trim($note), '[')) {
            return [];
        }

        $decoded = json_decode($note, true);
        if (! is_array($decoded)) {
            return [];
        }

        $picks = [];
        foreach ($decoded as $entry) {
            $name = is_array($entry) ? trim((string) ($entry['name'] ?? '')) : '';
            $qty  = is_array($entry) ? (int) ($entry['qty'] ?? 1) : 0;

            if ($name !== '' && $qty > 0) {
                $picks[] = ['name' => $name, 'qty' => $qty];
            }
        }

        return $picks;
    }

    /**
     * Turn the chosen product NAMES on an order's bundle lines into inventory
     * components: name => [inventory_item_id => qty per unit].
     *
     * The choices are stored by name because that is what the picker shows.
     * Names resolve through the app's own catalog (products.name -> products.id)
     * and from there through the same wp_product_maps table everything else
     * uses, so a bundle choice deducts exactly what that product deducts when
     * sold on its own.
     *
     * A name that matches several catalog rows is only used when they all
     * deduct the same thing - two rows for the same cream is fine, two rows
     * meaning different products is not, and guessing would quietly corrupt
     * stock. Anything unresolved is reported to the caller instead.
     *
     * @param  array<int,mixed>  $items
     * @return array<string,array<int,int>>
     */
    private function resolveBundleChoices(array $items): array
    {
        $names = [];
        foreach ($items as $line) {
            if (! is_array($line)) {
                continue;
            }
            foreach ($this->bundleChoices($line) as $pick) {
                $names[$pick['name']] = true;
            }
        }

        if (! $names) {
            return [];
        }

        $names = array_keys($names);

        $products = Product::whereIn('name', $names)->get(['id', 'name']);
        if ($products->isEmpty()) {
            return [];
        }

        $maps = WpProductMap::with('items')
            ->whereIn('wp_product_id', $products->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->get()
            ->keyBy('wp_product_id');

        $resolved = [];
        foreach ($names as $name) {
            $candidates = [];

            foreach ($products->where('name', $name) as $product) {
                /** @var WpProductMap|null $map */
                $map = $maps->get((string) $product->id);

                if (! $map || $map->skip_stock || $map->items->isEmpty()) {
                    continue;
                }

                $components = [];
                foreach ($map->items as $component) {
                    $components[(int) $component->inventory_item_id] = (int) $component->qty;
                }
                ksort($components);
                $candidates[json_encode($components)] = $components;
            }

            // One agreed answer, or nothing - never a guess between two.
            if (count($candidates) === 1) {
                $resolved[$name] = reset($candidates);
            }
        }

        return $resolved;
    }

    /**
     * Add stock back for an invoice that is being cancelled. Idempotent.
     *
     * @return int number of reversal movements written
     */
    public function reverseForInvoice(Invoice $invoice): int
    {
        return $this->restock($invoice, StockLedger::SALES_INVOICE_CANCEL);
    }

    /**
     * Add stock back for an invoice whose order is being RETURNED. The units go
     * back into the sellable bucket so they're immediately available again.
     * Idempotent and mutually exclusive with reverseForInvoice (stock can never
     * be added back twice for the same invoice).
     *
     * @return int number of return movements written
     */
    public function returnForInvoice(Invoice $invoice, ?string $reason = null): int
    {
        return $this->restock($invoice, StockLedger::CUSTOMER_RETURN, $reason);
    }

    /**
     * Shared restock routine for cancel/return: re-adds every SALE movement of
     * the invoice under the given movement type. No-op unless the invoice was
     * actually deducted and has not already been restored by a prior
     * cancel/return.
     */
    private function restock(Invoice $invoice, string $movementType, ?string $reason = null): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        if (! $this->alreadyDeducted($invoice) || $this->alreadyRestored($invoice)) {
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
                $movementType,
                StockLedger::BUCKET_SELLABLE,
                [
                    'source_type'    => self::SOURCE_TYPE,
                    'source_id'      => $invoice->id,
                    'reference'      => $sale->reference,
                    'reason'         => $reason,
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

    /** True once stock has been added back by either a cancel or a return. */
    private function alreadyRestored(Invoice $invoice): bool
    {
        return StockLedger::where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $invoice->id)
            ->whereIn('movement_type', [
                StockLedger::SALES_INVOICE_CANCEL,
                StockLedger::CUSTOMER_RETURN,
            ])
            ->exists();
    }
}
