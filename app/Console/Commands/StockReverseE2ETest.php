<?php

namespace App\Console\Commands;

use App\Http\Controllers\Inventory\GoodsReceiptController;
use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\StockLedger;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * End-to-end test for the "edit/delete a PO even after receiving goods" feature.
 *
 * Drives the REAL controllers and asserts that received stock is auto-reversed:
 *   A) DELETE a fully-received PO  -> stock back to baseline, GRN gone, ledger reversed.
 *   B) EDIT  a fully-received PO   -> stock back to baseline, PO pending, lines rewritten.
 *
 * Everything runs inside one transaction that is ALWAYS rolled back, so real
 * vendors / POs / stock / ledger are never touched.
 *
 *   php artisan stock:reverse-e2e
 */
class StockReverseE2ETest extends Command
{
    protected $signature = 'stock:reverse-e2e {--qty=25 : Quantity ordered/received per line}';

    protected $description = 'Verify received-goods stock is auto-reversed when a PO is deleted or edited';

    private int $failures = 0;

    public function handle(): int
    {
        $qty = (int) $this->option('qty');

        $items = InventoryItem::orderBy('id')->take(2)->get();
        if ($items->count() < 2) {
            $this->error('Need at least 2 inventory items to run this test.');
            return self::FAILURE;
        }

        // Baseline stock for the two items we will touch.
        $baseline = [];
        foreach ($items as $it) {
            $baseline[$it->id] = $this->stockOf($it->id);
        }
        $this->line('Baseline stock: ' . $this->fmtMap($baseline, $items));

        // Run both scenarios inside a transaction we always roll back.
        try {
            DB::transaction(function () use ($items, $qty, $baseline) {
                $this->scenarioDelete($items, $qty, $baseline);
                $this->scenarioEdit($items, $qty, $baseline);

                // Force rollback — this is a test, persist nothing.
                throw new RollbackSignal();
            });
        } catch (RollbackSignal $e) {
            $this->newLine();
            $this->info('Rolled back — no real data was modified.');
        }

        $this->newLine();
        if ($this->failures === 0) {
            $this->info('✓ ALL CHECKS PASSED');
            return self::SUCCESS;
        }

        $this->error("✗ {$this->failures} CHECK(S) FAILED");
        return self::FAILURE;
    }

    /** A) Delete a fully-received PO and verify stock is reversed to baseline. */
    private function scenarioDelete($items, int $qty, array $baseline): void
    {
        $this->section('SCENARIO A — delete a fully-received PO');

        [$po, $grn] = $this->setupReceivedPo($items, $qty);
        $this->line("   Setup: {$po->po_number} received, {$grn->reference_id} created");

        foreach ($items as $it) {
            $this->assert(
                $this->stockOf($it->id) === $baseline[$it->id] + $qty,
                "after receive, {$it->name} = baseline + {$qty}"
            );
        }

        $ledgerBefore = StockLedger::where('movement_type', StockLedger::GRN_REVERSAL)->count();

        app(PurchaseOrderController::class)->destroy($po);

        $this->assert(PurchaseOrder::find($po->id) === null, 'PO row deleted');
        $this->assert(GoodsReceipt::find($grn->id) === null, 'GRN row deleted');
        $this->assert(
            StockLedger::where('movement_type', StockLedger::GRN_REVERSAL)->count() === $ledgerBefore + $items->count(),
            'one grn_reversal ledger entry written per received line'
        );
        foreach ($items as $it) {
            $this->assert(
                $this->stockOf($it->id) === $baseline[$it->id],
                "after delete, {$it->name} back to baseline ({$baseline[$it->id]})"
            );
        }
    }

    /** B) Edit a fully-received PO and verify stock is reversed and PO reset to pending. */
    private function scenarioEdit($items, int $qty, array $baseline): void
    {
        $this->section('SCENARIO B — edit a fully-received PO');

        [$po, $grn] = $this->setupReceivedPo($items, $qty);
        $this->line("   Setup: {$po->po_number} received, {$grn->reference_id} created");

        // Edit with new quantities (smaller) on the same items.
        $newQty = max(1, (int) floor($qty / 2));
        $newItems = $items->map(fn ($it) => [
            'product_id'  => $it->id,
            'qty_ordered' => $newQty,
            'unit_cost'   => 50,
        ])->values()->all();

        $updated = app(PurchaseOrderController::class)->update(
            Request::create("/api/purchase-orders/{$po->id}", 'PUT', [
                'vendor_id'  => $po->vendor_id,
                'order_date' => now()->toDateString(),
                'tax_mode'   => 'exclusive',
                'items'      => $newItems,
            ]),
            $po->fresh()
        );

        $updated = PurchaseOrder::with('items')->find($po->id);

        $this->assert($updated->status === 'pending', "PO reset to pending (was received), got '{$updated->status}'");
        $this->assert((int) $updated->received_qty === 0, 'received_qty reset to 0 after edit');
        $this->assert((int) $updated->ordered_qty === $newQty * $items->count(), "ordered_qty reflects rewritten lines ({$newQty} each)");
        $this->assert(GoodsReceipt::find($grn->id) === null, 'GRN removed on edit');
        foreach ($items as $it) {
            $this->assert(
                $this->stockOf($it->id) === $baseline[$it->id],
                "after edit, {$it->name} reversed back to baseline ({$baseline[$it->id]})"
            );
        }
    }

    /** Create vendor -> pending PO -> full goods receipt for the given items. */
    private function setupReceivedPo($items, int $qty): array
    {
        $vendor = Vendor::create([
            'name'        => 'Reverse E2E Vendor',
            'email'       => 'reverse@e2e.test',
            'status'      => 'active',
        ]);

        $po = app(PurchaseOrderController::class)->store(Request::create('/api/purchase-orders', 'POST', [
            'vendor_id'  => $vendor->id,
            'order_date' => now()->toDateString(),
            'status'     => 'pending',
            'tax_mode'   => 'exclusive',
            'items'      => $items->map(fn ($it) => [
                'product_id'  => $it->id,
                'qty_ordered' => $qty,
                'unit_cost'   => 100,
            ])->values()->all(),
        ]));
        $po->load('items');

        $grn = app(GoodsReceiptController::class)->store(Request::create('/api/goods-receipts', 'POST', [
            'purchase_order_id' => $po->id,
            'vendor_id'         => $vendor->id,
            'received_date'     => now()->toDateString(),
            'items'             => $po->items->map(fn ($pi) => [
                'product_id'             => $pi->product_id,
                'purchase_order_item_id' => $pi->id,
                'qty_received'           => $qty,
                'unit_cost'              => (float) $pi->unit_cost,
            ])->values()->all(),
        ]));

        return [$po->fresh(), $grn];
    }

    private function stockOf($productId): int
    {
        return (int) InventoryStock::where('product_id', $productId)->value('sellable_qty');
    }

    private function assert(bool $ok, string $label): void
    {
        if ($ok) {
            $this->line("   <info>✓</info> {$label}");
        } else {
            $this->line("   <error>✗</error> {$label}");
            $this->failures++;
        }
    }

    private function fmtMap(array $map, $items): string
    {
        $byId = $items->pluck('name', 'id');
        return collect($map)->map(fn ($v, $k) => ($byId[$k] ?? "#$k") . "={$v}")->implode(', ');
    }

    private function section(string $t): void
    {
        $this->newLine();
        $this->info($t);
    }
}

/** Sentinel used only to force the wrapping transaction to roll back. */
class RollbackSignal extends \RuntimeException
{
}
