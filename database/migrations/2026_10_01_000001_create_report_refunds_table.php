<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refunds are not captured anywhere in the order flow, so the monthly
     * report takes them as a figure entered by hand per month. One row per
     * period, created the first time a value is saved for that month.
     */
    public function up(): void
    {
        Schema::create('report_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();   // YYYY-MM
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_refunds');
    }
};
