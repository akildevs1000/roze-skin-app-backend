<?php

namespace App\Console\Commands;

use App\Models\StockLedger;
use App\Services\StockSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the `reference` on sales-invoice stock ledger rows so the inventory
 * History table shows the invoice number (INV-XXXXXX) instead of the order
 * number (ORD-XXXXXX) that older rows were written with.
 *
 * Reference is derived deterministically from the linked invoice id
 * (source_id) using the same format as Invoice::generateReferenceId('INV'),
 * so the command is idempotent and safe to re-run.
 */
class BackfillStockLedgerInvoiceReference extends Command
{
    protected $signature = 'inventory:backfill-ledger-invoice-reference {--dry-run : Show what would change without writing}';

    protected $description = 'Set sales-invoice stock ledger reference to the invoice number (INV-XXXXXX)';

    public function handle(): int
    {
        $rows = StockLedger::where('source_type', StockSyncService::SOURCE_TYPE)
            ->whereNotNull('source_id')
            ->get(['id', 'source_id', 'reference']);

        if ($rows->isEmpty()) {
            $this->info('No sales-invoice ledger rows found. Nothing to do.');
            return self::SUCCESS;
        }

        $updates = 0;

        DB::transaction(function () use ($rows, &$updates) {
            foreach ($rows as $row) {
                $expected = 'INV-' . str_pad($row->source_id, 6, '0', STR_PAD_LEFT);

                if ($row->reference === $expected) {
                    continue;
                }

                $this->line(sprintf('Ledger #%d: %s -> %s', $row->id, $row->reference ?? 'null', $expected));

                if (! $this->option('dry-run')) {
                    StockLedger::whereKey($row->id)->update(['reference' => $expected]);
                }

                $updates++;
            }
        });

        $verb = $this->option('dry-run') ? 'would be updated' : 'updated';
        $this->info("{$updates} ledger row(s) {$verb}.");

        return self::SUCCESS;
    }
}
