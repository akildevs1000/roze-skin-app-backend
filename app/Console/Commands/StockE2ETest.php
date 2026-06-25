<?php

namespace App\Console\Commands;

use App\Http\Controllers\Inventory\GoodsReceiptController;
use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Http\Controllers\Inventory\VendorController;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderItem;
use App\Models\StockLedger;
use App\Models\WpProductMap;
use App\Services\StockSyncService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Full inventory end-to-end test driven through the REAL controllers:
 *   clear -> vendor -> purchase order -> goods receipt (stock = 100 each)
 *   -> convert a real order to an invoice -> stock deducts via the mapping.
 *
 *   php artisan stock:e2e --qty=100
 */
class StockE2ETest extends Command
{
    protected $signature = 'stock:e2e {--qty=100 : Stock quantity to receive for every item} {--cost=100 : Unit cost/rate to use for every line (testing)} {--no-convert : Stop after GRN; do not auto-convert an order}';

    protected $description = 'Clear PO/vendor/stock, rebuild stock via vendor->PO->GRN, then convert an order to an invoice';

    public function handle(): int
    {
        $qty  = (int) $this->option('qty');
        $cost = (float) $this->option('cost');

        $this->section('1) CLEARING purchase orders, vendors, goods receipts and stock');
        DB::transaction(function () {
            GoodsReceiptItem::query()->delete();
            GoodsReceipt::query()->delete();
            PurchaseOrderItem::query()->delete();
            PurchaseOrderAttachment::query()->delete();
            PurchaseOrder::query()->delete();
            StockLedger::query()->delete();
            InventoryStock::query()->delete();
            \App\Models\Vendor::query()->delete();
        });
        $this->line('   Cleared. All inventory items now at 0 stock, no ledger history.');

        $items = InventoryItem::orderBy('name')->get();
        $this->line('   Inventory items: ' . $items->count());

        // ---- 2) Vendor -------------------------------------------------------
        $this->section('2) Creating VENDOR (via VendorController::store)');
        $vendor = app(VendorController::class)->store(Request::create('/api/vendors', 'POST', [
            'company_name' => 'E2E Test Supplier',
            'email'        => 'supplier@e2e.test',
            'mobile'       => '+971500000000',
            'city'         => 'Dubai',
            'country'      => 'UAE',
            'status'       => 'active',
        ]));
        $this->line("   Vendor #{$vendor->id}: {$vendor->name}");

        // ---- 3) Purchase Order ----------------------------------------------
        $this->section('3) Creating PURCHASE ORDER for all items (via PurchaseOrderController::store)');
        $poItems = $items->map(fn ($it) => [
            'product_id'  => $it->id,
            'qty_ordered' => $qty,
            'unit_cost'   => $cost,
        ])->values()->all();

        $po = app(PurchaseOrderController::class)->store(Request::create('/api/purchase-orders', 'POST', [
            'vendor_id'  => $vendor->id,
            'order_date' => now()->toDateString(),
            'status'     => 'pending',
            'tax_mode'   => 'exclusive',
            'items'      => $poItems,
        ]));
        $po->load('items');
        $this->line("   {$po->po_number}: {$po->items->count()} lines, {$po->ordered_qty} units ordered, status={$po->status}");

        // ---- 4) Goods Receipt (stock increases here) ------------------------
        $this->section("4) GOODS RECEIVING {$qty} of each (via GoodsReceiptController::store)");
        $grnItems = $po->items->map(fn ($pi) => [
            'product_id'             => $pi->product_id,
            'purchase_order_item_id' => $pi->id,
            'qty_received'           => $qty,
            'unit_cost'              => (float) $pi->unit_cost,
        ])->values()->all();

        $grn = app(GoodsReceiptController::class)->store(Request::create('/api/goods-receipts', 'POST', [
            'purchase_order_id' => $po->id,
            'vendor_id'         => $vendor->id,
            'received_date'     => now()->toDateString(),
            'items'             => $grnItems,
        ]));
        $po->refresh();
        $this->line("   {$grn->reference_id}: received {$grn->total_qty} units. PO status now: {$po->status}");

        // verify stock
        $this->section('   Stock after receiving:');
        $allHundred = true;
        foreach ($items as $it) {
            $s = (int) InventoryStock::where('product_id', $it->id)->value('sellable_qty');
            if ($s !== $qty) {
                $allHundred = false;
            }
            $this->line(sprintf('     %-34s %d', $it->name, $s));
        }
        $this->line($allHundred ? "   ✓ every item = {$qty}" : '   ✗ mismatch — check above');

        if ($this->option('no-convert')) {
            $this->newLine();
            $this->info('Setup done. Vendor, PO and GRN created; stock is available.');
            $this->comment('Now create an order and Convert to Invoice from the UI to watch stock deduct.');
            return self::SUCCESS;
        }

        // ---- 5) Convert an order to an invoice ------------------------------
        $this->section('5) Convert a real ORDER -> INVOICE (stock deducts via mapping)');
        $order = $this->pickRichestOrder();
        if (! $order) {
            $this->warn('   No order with mapped items found — skipping convert step.');
            return self::SUCCESS;
        }

        $this->line("   Chosen order: id={$order->id}, order_id={$order->order_id}");
        foreach ((array) $order->items as $l) {
            $this->line(sprintf('     line: %-46s (wp_id %s) x%s',
                $this->short($l['item'] ?? ''), $l['product_id'] ?? '?', $l['quantity'] ?? '?'));
        }

        // snapshot affected items before
        $before = InventoryStock::pluck('sellable_qty', 'product_id')->all();

        $invoice = Invoice::updateOrCreate(
            ['order_id' => $order->id],
            ['customer_id' => $order->customer_id, 'status' => 'Paid', 'converted_to_invoice_at' => now()]
        );
        $result = app(StockSyncService::class)->deductForInvoice($invoice);

        $this->section('   Deduction result:');
        $this->line('   deducted movements: ' . $result['deducted']);
        if (! empty($result['unmapped'])) {
            foreach ($result['unmapped'] as $u) {
                $this->warn("   UNMAPPED: {$u['product_id']}  {$u['name']}");
            }
        }

        $this->section('   Stock change (only affected items):');
        $after = InventoryStock::pluck('sellable_qty', 'product_id')->all();
        $nameById = $items->pluck('name', 'id');
        foreach ($after as $pid => $qtyAfter) {
            $b = (int) ($before[$pid] ?? 0);
            if ($b !== (int) $qtyAfter) {
                $this->line(sprintf('     %-34s %d -> %d  (-%d)', $nameById[$pid] ?? "#$pid", $b, $qtyAfter, $b - $qtyAfter));
            }
        }

        $this->newLine();
        $this->info('E2E complete. Vendor, PO, GRN and the invoice are all visible in the UI.');
        $this->comment('Tip: re-run any time with  php artisan stock:e2e --qty=100');

        return self::SUCCESS;
    }

    /** Pick the order whose mapped lines produce the most stock movements (best demo). */
    private function pickRichestOrder(): ?Order
    {
        $maps = WpProductMap::with('items')->get()->keyBy('wp_product_id');

        $bestId = null; $bestScore = -1;
        foreach (DB::table('orders')->select('id', 'items')->get() as $row) {
            $lines = json_decode($row->items ?? '', true);
            if (! is_array($lines)) {
                continue;
            }
            $score = 0;
            foreach ($lines as $l) {
                $pid = isset($l['product_id']) ? (string) $l['product_id'] : '';
                $m = $maps->get($pid);
                if ($m && ! $m->skip_stock) {
                    $score += $m->items->count();
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $row->id;
            }
        }

        return $bestId ? Order::find($bestId) : null;
    }

    private function section(string $t): void
    {
        $this->newLine();
        $this->info($t);
    }

    private function short(string $s): string
    {
        return strlen($s) > 46 ? substr($s, 0, 46) . '…' : $s;
    }
}
