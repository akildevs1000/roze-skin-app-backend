<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * orders.order_id holds the store's own order reference, not a number the
     * system ever does arithmetic on. It was a bigint, so references containing
     * letters could not be entered at all.
     *
     * Every use of it is an equality lookup, and the column carries no index or
     * foreign key, so widening it to text is safe. Note that ordering by it
     * becomes lexicographic rather than numeric.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE orders ALTER COLUMN order_id DROP DEFAULT');
        DB::statement('ALTER TABLE orders ALTER COLUMN order_id TYPE varchar(50) USING order_id::varchar');
        DB::statement("ALTER TABLE orders ALTER COLUMN order_id SET DEFAULT '0'");
    }

    /**
     * Only reversible while every reference is still numeric. Once a reference
     * with letters has been saved, going back would lose it, so this refuses
     * rather than destroying data.
     */
    public function down(): void
    {
        $nonNumeric = DB::table('orders')
            ->whereRaw("order_id !~ '^[0-9]+$'")
            ->count();

        if ($nonNumeric > 0) {
            throw new \RuntimeException(
                "Cannot revert: {$nonNumeric} order(s) have a reference containing letters."
            );
        }

        DB::statement('ALTER TABLE orders ALTER COLUMN order_id DROP DEFAULT');
        DB::statement('ALTER TABLE orders ALTER COLUMN order_id TYPE bigint USING order_id::bigint');
        DB::statement('ALTER TABLE orders ALTER COLUMN order_id SET DEFAULT 0');
    }
};
