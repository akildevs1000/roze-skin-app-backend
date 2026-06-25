<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WpProductMapItem extends Model
{
    protected $fillable = [
        'wp_product_map_id',
        'inventory_item_id',
        'qty',
    ];

    public function map()
    {
        return $this->belongsTo(WpProductMap::class, 'wp_product_map_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
