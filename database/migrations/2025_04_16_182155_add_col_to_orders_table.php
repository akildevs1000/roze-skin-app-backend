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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger("business_source_id")->default(0);
            $table->unsignedBigInteger("delivery_service_id")->default(0);
            $table->unsignedBigInteger("tracking_number")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn("business_source_id");
            $table->dropColumn("delivery_service_id");
            $table->dropColumn("tracking_number");
        });
    }
};
