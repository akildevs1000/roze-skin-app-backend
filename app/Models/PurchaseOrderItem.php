<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $guarded = [];

    protected $appends = ['pending_qty'];

    public function purchase_order()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(InventoryItem::class, 'product_id');
    }

    public function getPendingQtyAttribute()
    {
        return max(0, (int) $this->qty_ordered - (int) $this->qty_received);
    }
}
