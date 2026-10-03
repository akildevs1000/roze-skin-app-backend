<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * order_items.order_id holds the same store reference as orders.order_id,
     * which became text so references containing letters could be entered. This
     * column was left behind, so app:insert-order-items threw on every run that
     * met one:
     *
     *   invalid input syntax for type bigint: "NAEI90006137136"
     *
     * Every use is an equality lookup and the index is rebuilt by the type
     * change, so widening it is safe.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE order_items ALTER COLUMN order_id TYPE varchar(50) USING order_id::varchar');
    }

    /**
     * Only reversible while every reference is still numeric. Once one with
     * letters has been stored, going back would lose it, so this refuses rather
     * than destroying data.
     */
    public function down(): void
    {
        $nonNumeric = DB::table('order_items')
            ->whereRaw("order_id !~ '^[0-9]+$'")
            ->count();

        if ($nonNumeric > 0) {
            throw new \RuntimeException(
                "Cannot revert: {$nonNumeric} order item(s) have a reference containing letters."
            );
        }

        DB::statement('ALTER TABLE order_items ALTER COLUMN order_id TYPE bigint USING order_id::bigint');
    }
};
