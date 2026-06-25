<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core inventory tables.
     *
     * inventory_stocks  -> authoritative on-hand balance per product, split into
     *                      sellable (available) and non_sellable (damaged/expired) buckets.
     * stock_ledgers     -> immutable journal: one row for every stock change (rule #12).
     */
    public function up()
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            $table->integer('sellable_qty')->default(0);     // available stock
            $table->integer('non_sellable_qty')->default(0); // damaged / expired
            $table->integer('reorder_level')->default(0);    // low-stock threshold
            $table->timestamps();

            $table->index('product_id');
        });

        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('movement_type');           // grn_receive, sale, adjustment_increase, ...
            $table->string('bucket')->default('sellable'); // sellable | non_sellable
            $table->integer('quantity');               // signed: +in / -out
            $table->integer('balance_after');          // bucket balance after this movement
            $table->string('source_type')->nullable(); // e.g. goods_receipt, sales_invoice, shipment
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference')->nullable();   // human reference like GRN-000012
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index(['source_type', 'source_id']);
            $table->index('movement_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_ledgers');
        Schema::dropIfExists('inventory_stocks');
    }
};
