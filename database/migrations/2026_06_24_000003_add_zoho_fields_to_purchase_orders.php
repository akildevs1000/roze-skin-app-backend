<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remaining Zoho-style Purchase Order fields:
     * delivery destination (warehouse|customer), shipment preference,
     * tax handling mode + per-line tax, and the discount level.
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'delivery_type')) $table->string('delivery_type')->default('warehouse')->after('vendor_id'); // warehouse | customer
            if (! Schema::hasColumn('purchase_orders', 'warehouse_id')) $table->unsignedBigInteger('warehouse_id')->nullable()->after('delivery_type');
            if (! Schema::hasColumn('purchase_orders', 'customer_id')) $table->unsignedBigInteger('customer_id')->nullable()->after('warehouse_id');
            if (! Schema::hasColumn('purchase_orders', 'shipment_preference')) $table->string('shipment_preference')->nullable()->after('payment_terms');
            if (! Schema::hasColumn('purchase_orders', 'tax_mode')) $table->string('tax_mode')->default('exclusive')->after('discount_type'); // exclusive | inclusive
            if (! Schema::hasColumn('purchase_orders', 'discount_level')) $table->string('discount_level')->default('transaction')->after('tax_mode'); // transaction | line
            if (! Schema::hasColumn('purchase_orders', 'tax_total')) $table->decimal('tax_total', 12, 2)->default(0)->after('sub_total');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'tax_name')) $table->string('tax_name')->nullable()->after('unit_cost');
            if (! Schema::hasColumn('purchase_order_items', 'tax_rate')) $table->decimal('tax_rate', 8, 2)->default(0)->after('tax_name');
            if (! Schema::hasColumn('purchase_order_items', 'tax_amount')) $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
        });
    }

    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach (['delivery_type', 'warehouse_id', 'customer_id', 'shipment_preference', 'tax_mode', 'discount_level', 'tax_total'] as $col) {
                if (Schema::hasColumn('purchase_orders', $col)) $table->dropColumn($col);
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            foreach (['tax_name', 'tax_rate', 'tax_amount'] as $col) {
                if (Schema::hasColumn('purchase_order_items', $col)) $table->dropColumn($col);
            }
        });
    }
};
