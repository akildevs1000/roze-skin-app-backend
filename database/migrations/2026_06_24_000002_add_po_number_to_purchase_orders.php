<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give purchase orders a real, sequential PO number that is independent of the
     * auto-increment id (which can have gaps from deleted rows). Existing rows are
     * backfilled from their id so numbering stays continuous.
     */
    public function up()
    {
        if (! Schema::hasColumn('purchase_orders', 'po_number')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('po_number')->nullable()->after('id');
            });
        }

        // Backfill existing rows: PO-000001, PO-000002, ...
        DB::statement("UPDATE purchase_orders SET po_number = 'PO-' || lpad(id::text, 6, '0') WHERE po_number IS NULL");
    }

    public function down()
    {
        if (Schema::hasColumn('purchase_orders', 'po_number')) {
            Schema::table('purchase_orders', fn (Blueprint $t) => $t->dropColumn('po_number'));
        }
    }
};
