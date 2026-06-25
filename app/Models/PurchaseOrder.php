<?php

namespace App\Models;

use App\Traits\HasReferenceId;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasReferenceId;

    protected $guarded = [];

    protected $appends = ['reference_id', 'received_qty', 'ordered_qty', 'pending_qty', 'vendor_name'];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function attachments()
    {
        return $this->hasMany(PurchaseOrderAttachment::class);
    }

    public function getVendorNameAttribute()
    {
        return $this->vendor ? $this->vendor->name : $this->attributes['supplier_name'] ?? null;
    }

    public function getReferenceIdAttribute()
    {
        return $this->po_number ?: $this->generateReferenceId('PO');
    }

    /** Next sequential, gap-free PO number (e.g. PO-000005). */
    public static function nextNumber(): string
    {
        $max = static::pluck('po_number')
            ->map(fn ($x) => (int) preg_replace('/\D/', '', (string) $x))
            ->max() ?? 0;

        return 'PO-' . str_pad($max + 1, 6, '0', STR_PAD_LEFT);
    }

    public function getOrderedQtyAttribute()
    {
        return (int) $this->items->sum('qty_ordered');
    }

    public function getReceivedQtyAttribute()
    {
        return (int) $this->items->sum('qty_received');
    }

    public function getPendingQtyAttribute()
    {
        return max(0, $this->ordered_qty - $this->received_qty);
    }
}
