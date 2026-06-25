<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryStock extends Model
{
    protected $guarded = [];

    protected $appends = ['total_qty', 'is_low'];

    public function product()
    {
        return $this->belongsTo(InventoryItem::class, 'product_id');
    }

    public function getTotalQtyAttribute()
    {
        return (int) $this->sellable_qty + (int) $this->non_sellable_qty;
    }

    public function getIsLowAttribute()
    {
        return $this->reorder_level > 0 && $this->sellable_qty <= $this->reorder_level;
    }
}
