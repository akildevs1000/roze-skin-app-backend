<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The standalone Sales Invoice and Shipment modules were removed from the
     * inventory system (invoicing/shipping will be handled elsewhere later).
     * Drop their (empty) tables. The nullable sales_invoice_id / shipment_id
     * columns on stock_returns are intentionally left in place for future linking.
     */
    public function up()
    {
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('sales_invoice_items');
        Schema::dropIfExists('sales_invoices');
    }

    public function down()
    {
        // Sales Invoice and Shipment modules were intentionally removed; nothing to restore.
    }
};
