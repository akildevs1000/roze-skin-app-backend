<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * address_1 and address_2 were varchar(255), and the API validated to
     * match. A customer in Sharjah typed a 291-character address on 31 October
     * and the order was rejected: WooCommerce builds this line by joining
     * flat, building, street, area and landmark, so a detailed address passes
     * 255 easily.
     *
     * The order was lost silently - the webhook saw a redirect, followed it to
     * a 404 page, and logged "endpoint not found" rather than "address too
     * long". Truncating instead would be worse: the courier needs the whole
     * address to deliver.
     *
     * text in PostgreSQL costs nothing over varchar; the length limit was the
     * only thing these columns gained from being varchar.
     */
    public function up(): void
    {
        foreach (['shipping_addresses', 'billing_addresses'] as $table) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN address_1 TYPE text");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN address_2 TYPE text");
        }
    }

    /**
     * Only reversible while every stored address still fits. Going back with a
     * longer one present would silently cut a delivery address in half, so
     * this refuses rather than destroying data.
     */
    public function down(): void
    {
        foreach (['shipping_addresses', 'billing_addresses'] as $table) {
            $tooLong = DB::table($table)
                ->whereRaw('length(address_1) > 255 OR length(address_2) > 255')
                ->count();

            if ($tooLong > 0) {
                throw new \RuntimeException(
                    "Cannot revert {$table}: {$tooLong} address(es) are longer than 255 characters."
                );
            }

            DB::statement("ALTER TABLE {$table} ALTER COLUMN address_1 TYPE varchar(255)");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN address_2 TYPE varchar(255)");
        }
    }
};
