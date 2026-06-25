<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The inventory module's own catalog of stockable items.
     * Stock, purchasing, adjustments and returns all reference these (via the
     * existing product_id columns, which now point at inventory_items.id).
     */
    public function up()
    {
        if (! Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->nullable();
                $table->string('name');
                $table->string('description')->nullable();
                $table->decimal('unit_cost', 12, 2)->default(0); // standard cost for valuation
                $table->string('status')->default('active');     // active | inactive
                $table->timestamps();

                $table->index('name');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('inventory_items');
    }
};
