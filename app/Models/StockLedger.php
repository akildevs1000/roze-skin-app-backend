<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLedger extends Model
{
    protected $guarded = [];

    /** Movement type constants — every stock change is one of these. */
    const GRN_RECEIVE          = 'grn_receive';
    const ADJUSTMENT_INCREASE  = 'adjustment_increase';
    const ADJUSTMENT_DECREASE  = 'adjustment_decrease';
    const SALE                 = 'sale';
    const SALES_INVOICE_CANCEL = 'sales_invoice_cancel';
    const SHIPMENT_CANCEL      = 'shipment_cancel';
    const CUSTOMER_RETURN      = 'customer_return';
    const RTO                  = 'rto';

    /** Buckets. */
    const BUCKET_SELLABLE     = 'sellable';
    const BUCKET_NON_SELLABLE = 'non_sellable';

    protected $appends = ['movement_label'];

    public function product()
    {
        return $this->belongsTo(InventoryItem::class, 'product_id');
    }

    public function getMovementLabelAttribute()
    {
        $labels = [
            self::GRN_RECEIVE          => 'Goods Received',
            self::ADJUSTMENT_INCREASE  => 'Adjustment (+)',
            self::ADJUSTMENT_DECREASE  => 'Adjustment (-)',
            self::SALE                 => 'Sale',
            self::SALES_INVOICE_CANCEL => 'Invoice Cancelled',
            self::SHIPMENT_CANCEL      => 'Shipment Cancelled',
            self::CUSTOMER_RETURN      => 'Customer Return',
            self::RTO                  => 'RTO',
        ];

        return $labels[$this->movement_type] ?? ucwords(str_replace('_', ' ', $this->movement_type));
    }
}
