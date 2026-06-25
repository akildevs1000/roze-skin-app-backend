<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purchasing + Goods Receiving.
     *
     * A Purchase Order NEVER changes stock (rule #1). Stock only increases when a
     * Goods Receipt Note (GRN) is recorded (rule #2). Partial receiving is supported
     * via purchase_order_items.qty_received (rule #3).
     */
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name')->nullable();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            // draft | pending | partially_received | received | cancelled
            $table->string('status')->default('pending');
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('qty_ordered')->default(0);
            $table->integer('qty_received')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('product_id');
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id')->nullable(); // null = direct receipt
            $table->string('supplier_name')->nullable();
            $table->date('received_date');
            $table->string('status')->default('received');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->integer('qty_received')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->index('goods_receipt_id');
            $table->index('product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
