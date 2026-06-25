<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\StockLedger;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSalesSeeder extends Seeder
{
    /**
     * Demo stock movements so the "Sold quantity by day" chart and the
     * Received / Sold summary cards have something to show.
     *
     * For each seeded item it writes one opening goods-receipt (~25 days ago)
     * followed by random daily sales across the last ~21 days, keeping a
     * running sellable balance and syncing inventory_stocks to the final value.
     *
     * Re-runnable: it first clears its own demo rows (reference prefix DEMO-).
     *
     *   php artisan db:seed --class=DemoSalesSeeder
     */
    /** Demo customer pool — sales are attributed to a random one of these. */
    private const CUSTOMERS = [
        'Sara Ahmed', 'Mohammed Ali', 'Fatima Hassan', 'Layla Khalid',
        'Omar Saeed', 'Aisha Rahman', 'Yousef Nasser', 'Mariam Abdullah',
        'Khalid Mansour', 'Noura Salem',
    ];

    public function run()
    {
        $items = InventoryItem::orderBy('id')->take(6)->get();

        if ($items->isEmpty()) {
            $this->command->warn('No inventory_items found — run InventoryItemSeeder first.');
            return;
        }

        foreach ($items as $item) {
            $pid = $item->id;

            // wipe previous demo rows for this product so re-runs don't stack
            StockLedger::where('product_id', $pid)->where('reference', 'LIKE', 'DEMO-%')->delete();

            $rows    = [];
            $balance = 0;

            // ---- opening goods receipt (~25 days ago) ----
            $openQty  = rand(300, 600);
            $balance += $openQty;
            $openDate = Carbon::now()->subDays(25)->setTime(9, rand(0, 59));
            $rows[]   = $this->row($pid, StockLedger::GRN_RECEIVE, $openQty, $balance, 'DEMO-GRN-' . $pid, 'Opening stock (demo)', $openDate);

            // ---- daily sales across the last 21 days ----
            for ($d = 21; $d >= 0; $d--) {
                $txns = rand(0, 3); // some days have no sales
                for ($t = 0; $t < $txns; $t++) {
                    if ($balance <= 0) {
                        break;
                    }
                    $qty      = rand(1, min(6, $balance));
                    $balance -= $qty;
                    $date     = Carbon::now()->subDays($d)->setTime(rand(10, 18), rand(0, 59));
                    $customer = self::CUSTOMERS[array_rand(self::CUSTOMERS)];
                    $rows[]   = $this->row($pid, StockLedger::SALE, -$qty, $balance, 'DEMO-INV-' . $pid . '-' . $d . $t, 'Sample sale (demo)', $date, $customer);
                }
            }

            DB::table('stock_ledgers')->insert($rows);

            // keep the authoritative balance in sync with the ledger
            DB::table('inventory_stocks')->updateOrInsert(
                ['product_id' => $pid],
                ['sellable_qty' => $balance, 'updated_at' => Carbon::now()]
            );

            $this->command->info("Seeded {$item->name}: opening {$openQty}, balance {$balance}.");
        }
    }

    private function row($pid, $type, $qty, $balanceAfter, $reference, $reason, Carbon $at, $customer = null)
    {
        return [
            'product_id'    => $pid,
            'movement_type' => $type,
            'bucket'        => StockLedger::BUCKET_SELLABLE,
            'quantity'      => $qty,
            'balance_after' => $balanceAfter,
            'source_type'   => 'demo',
            'source_id'     => null,
            'reference'     => $reference,
            'customer_name' => $customer,
            'reason'        => $reason,
            'user_id'       => null,
            'created_at'    => $at,
            'updated_at'    => $at,
        ];
    }
}
