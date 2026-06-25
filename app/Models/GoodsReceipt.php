<?php

namespace App\Models;

use App\Traits\HasReferenceId;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    use HasReferenceId;

    protected $guarded = [];

    protected $appends = ['reference_id', 'total_qty', 'vendor_name'];

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function purchase_order()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function getVendorNameAttribute()
    {
        return $this->vendor ? $this->vendor->name : $this->attributes['supplier_name'] ?? null;
    }

    public function getReferenceIdAttribute()
    {
        return $this->generateReferenceId('GRN');
    }

    public function getTotalQtyAttribute()
    {
        return (int) $this->items->sum('qty_received');
    }
}
