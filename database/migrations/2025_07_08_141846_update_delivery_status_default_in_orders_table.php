<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            // Step 1: Replace NULLs with default value
            DB::table('orders')
                ->whereNull('delivery_status')
                ->update(['delivery_status' => '---']);

            // Step 2: Change column to NOT NULL with default
            Schema::table('orders', function (Blueprint $table) {
                $table->string('delivery_status')->default('---')->nullable(false)->change();
            });
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
            $table->string('delivery_status')->nullable()->default(null)->change();
        });
    }
};
