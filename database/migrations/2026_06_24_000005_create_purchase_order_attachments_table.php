<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Files attached to a Purchase Order (max 10 files, 10MB each on the form). */
    public function up()
    {
        Schema::create('purchase_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('path');                 // relative to public/ (e.g. purchase_orders/abc.pdf)
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('purchase_order_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_attachments');
    }
};
