<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer the movement belongs to (for sales). Denormalised onto the ledger
     * so the item History table can show "Invoice # / Customer" without joining
     * back to the originating sales document.
     */
    public function up()
    {
        Schema::table('stock_ledgers', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('reference');
        });
    }

    public function down()
    {
        Schema::table('stock_ledgers', function (Blueprint $table) {
            $table->dropColumn('customer_name');
        });
    }
};
