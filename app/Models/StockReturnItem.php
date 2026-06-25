<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReturnItem extends Model
{
    protected $guarded = [];

    public function stock_return()
    {
        return $this->belongsTo(StockReturn::class);
    }

    public function product()
    {
        return $this->belongsTo(InventoryItem::class, 'product_id');
    }

    /** Sellable items go back to available stock; damaged/expired go to non-sellable. */
    public function bucket()
    {
        return $this->condition === 'sellable' ? 'sellable' : 'non_sellable';
    }
}
