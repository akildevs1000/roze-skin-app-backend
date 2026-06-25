<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer Returns and RTO (Return To Origin).
     *
     * Each returned line carries a condition (rule #9):
     *   - sellable  -> goes back to available stock   (rules #10)
     *   - damaged   -> goes to non-sellable stock      (rule #11)
     *   - expired   -> goes to non-sellable stock      (rule #11)
     */
    public function up()
    {
        Schema::create('stock_returns', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('customer_return'); // customer_return | rto
            $table->unsignedBigInteger('sales_invoice_id')->nullable();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->date('return_date');
            $table->string('status')->default('completed');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('type');
        });

        Schema::create('stock_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_return_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity')->default(1);
            $table->string('condition')->default('sellable'); // sellable | damaged | expired
            $table->timestamps();

            $table->index('stock_return_id');
            $table->index('product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_return_items');
        Schema::dropIfExists('stock_returns');
    }
};
