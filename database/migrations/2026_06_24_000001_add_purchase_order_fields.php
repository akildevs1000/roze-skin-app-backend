<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Richer purchase-order header fields (Zoho-style form):
     * reference#, payment terms, order-level discount, and terms & conditions.
     * (expected_date is reused as the Delivery Date; notes already exists.)
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'reference')) $table->string('reference')->nullable()->after('vendor_id');
            if (! Schema::hasColumn('purchase_orders', 'payment_terms')) $table->string('payment_terms')->nullable()->after('expected_date');
            if (! Schema::hasColumn('purchase_orders', 'discount')) $table->decimal('discount', 12, 2)->default(0)->after('total');
            if (! Schema::hasColumn('purchase_orders', 'discount_type')) $table->string('discount_type')->default('percentage')->after('discount'); // percentage | amount
            if (! Schema::hasColumn('purchase_orders', 'sub_total')) $table->decimal('sub_total', 12, 2)->default(0)->after('discount_type');
            if (! Schema::hasColumn('purchase_orders', 'terms')) $table->text('terms')->nullable()->after('notes');
        });
    }

    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach (['reference', 'payment_terms', 'discount', 'discount_type', 'sub_total', 'terms'] as $col) {
                if (Schema::hasColumn('purchase_orders', $col)) $table->dropColumn($col);
            }
        });
    }
};
