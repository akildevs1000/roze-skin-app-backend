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
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->onDelete('cascade');
            $table->integer('qty_added')->default(0);
            $table->integer('qty_available')->default(0);
            $table->string('reference')->nullable();
            $table->date('stock_date')->nullable(); // new column for filtering by date
            $table->timestamps();
        });

        // $stocks = StockEntry::where('stock_date', '>=', '2025-09-01')
        //     ->where('stock_date', '<=', '2025-09-24')
        //     ->get();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_entries');
    }
};
