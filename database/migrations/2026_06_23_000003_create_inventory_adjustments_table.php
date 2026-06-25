<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual inventory adjustments — increase or decrease with a mandatory reason (rule #4).
     */
    public function up()
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('type'); // increase | decrease
            $table->string('bucket')->default('sellable'); // sellable | non_sellable
            $table->integer('quantity'); // always positive magnitude
            $table->text('reason');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
