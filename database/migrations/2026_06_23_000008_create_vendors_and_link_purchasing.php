<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendors (suppliers) are managed as their own entity and linked to purchasing.
     * Purchase Orders and Goods Receipts now reference a vendor_id instead of a
     * free-text supplier name.
     */
    public function up()
    {
        if (! Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('contact_person')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('active'); // active | inactive
                $table->timestamps();

                $table->index('name');
            });
        }

        if (! Schema::hasColumn('purchase_orders', 'vendor_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('id');
                $table->index('vendor_id');
            });
        }

        if (! Schema::hasColumn('goods_receipts', 'vendor_id')) {
            Schema::table('goods_receipts', function (Blueprint $table) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('purchase_order_id');
                $table->index('vendor_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('purchase_orders', 'vendor_id')) {
            Schema::table('purchase_orders', fn (Blueprint $t) => $t->dropColumn('vendor_id'));
        }
        if (Schema::hasColumn('goods_receipts', 'vendor_id')) {
            Schema::table('goods_receipts', fn (Blueprint $t) => $t->dropColumn('vendor_id'));
        }
        Schema::dropIfExists('vendors');
    }
};
