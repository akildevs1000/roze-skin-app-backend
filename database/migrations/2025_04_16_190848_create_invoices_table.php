<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("customer_id")->default(0);
            $table->unsignedBigInteger("order_id")->default(0);
            $table->unsignedBigInteger("business_source_id")->default(0);
            $table->unsignedBigInteger("delivery_service_id")->default(0);
            $table->unsignedBigInteger("tracking_number")->default(0);
            $table->string("status")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
