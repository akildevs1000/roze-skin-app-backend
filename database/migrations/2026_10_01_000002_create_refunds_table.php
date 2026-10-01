<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A refund recorded against a real order.
     *
     * The customer details are copied in rather than only referenced, so the
     * record still reads correctly later even if the customer is edited or
     * removed. order_id is what makes stock restoration possible at all:
     * putting units back needs the order's lines, not just a name.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->default(0);
            $table->string('customer_name', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->decimal('order_value', 12, 2)->default(0);
            $table->decimal('refund_value', 12, 2)->default(0);
            $table->string('reason', 255)->nullable();
            $table->boolean('restocked')->default(false);
            $table->unsignedInteger('restocked_units')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
