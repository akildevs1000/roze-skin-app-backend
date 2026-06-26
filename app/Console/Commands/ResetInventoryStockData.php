<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipe transactional inventory/stock data so you can rebuild the flow from
 * scratch (vendor -> purchase order -> goods receipt -> order -> invoice).
 *
 * KEEPS all master data: inventory_items, vendors, warehouses, wp_product_maps.
 * Truncated tables have their identity sequences restarted, so the next PO is
 * PO-000001 again. inventory_stocks rows are preserved but their quantities are
 * zeroed (reorder_level is left intact).
 */
class ResetInventoryStockData extends Command
{
    protected $signature = 'inventory:reset-stock-data {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe transactional stock data (ledger, GRNs, POs, adjustments, returns) and zero on-hand balances. Master data is kept.';

    /** Tables fully truncated (identity restarted). Order is FK-safe via CASCADE. */
    private array $truncate = [
        'stock_return_items',
        'stock_returns',
        'inventory_adjustments',
        'goods_receipt_items',
        'goods_receipts',
        'purchase_order_attachments',
        'purchase_order_items',
        'purchase_orders',
        'stock_ledgers',
    ];

    public function handle(): int
    {
        $this->warn('This will DELETE all stock transactions and zero on-hand balances.');
        $this->line('Kept: inventory_items, vendors, warehouses, wp_product_maps.');

        foreach ($this->truncate as $t) {
            $this->line(sprintf('  %-28s %d rows', $t, DB::table($t)->count()));
        }
        $this->line(sprintf('  %-28s %d rows (will be zeroed, not deleted)', 'inventory_stocks', DB::table('inventory_stocks')->count()));

        if (! $this->option('force') && ! $this->confirm('Proceed?')) {
            $this->info('Aborted. Nothing changed.');
            return self::SUCCESS;
        }

        DB::transaction(function () {
            // One statement: truncate the whole set, restart their sequences,
            // and CASCADE to satisfy any cross-FKs within the set.
            $list = implode(', ', $this->truncate);
            DB::statement("TRUNCATE TABLE {$list} RESTART IDENTITY CASCADE");

            DB::table('inventory_stocks')->update([
                'sellable_qty'     => 0,
                'non_sellable_qty' => 0,
            ]);
        });

        $this->info('Done. Transactional stock data cleared and on-hand balances reset to 0.');
        return self::SUCCESS;
    }
}
