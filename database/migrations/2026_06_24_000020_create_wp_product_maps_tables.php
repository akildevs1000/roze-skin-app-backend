<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps WordPress order line-items to inventory items.
 *
 * WordPress orders only carry a `product_id` and a (non-unique) name, neither of
 * which matches our inventory catalogue. These tables translate one WordPress
 * product_id into zero, one, or many inventory items so that converting an order
 * to an invoice can decrement the correct stock.
 *
 *  - simple product   -> one wp_product_map_items row (qty 1)
 *  - fixed pack (kit)  -> several wp_product_map_items rows (one per component)
 *  - "Any 3" bundle    -> skip_stock = true, no items (its components arrive as
 *                         their own rate-0 lines and are mapped individually)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_product_maps', function (Blueprint $table) {
            $table->id();
            $table->string('wp_product_id')->unique();   // WordPress product_id (kept as string: payload sends both int and string)
            $table->string('wp_name')->nullable();       // snapshot of the latest name seen, for admin readability
            $table->boolean('skip_stock')->default(false); // virtual line (e.g. bundle parent) — deduct nothing
            $table->timestamps();
        });

        Schema::create('wp_product_map_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_product_map_id')->constrained('wp_product_maps')->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_item_id'); // -> inventory_items.id
            $table->unsignedInteger('qty')->default(1);      // units of this inventory item per 1 ordered unit
            $table->timestamps();

            $table->index('inventory_item_id');
            $table->unique(['wp_product_map_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_product_map_items');
        Schema::dropIfExists('wp_product_maps');
    }
};
