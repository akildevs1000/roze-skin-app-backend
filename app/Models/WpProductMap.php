<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A WordPress product_id and the inventory item(s) it should decrement.
 *
 * @see \App\Services\StockSyncService
 */
class WpProductMap extends Model
{
    protected $fillable = [
        'wp_product_id',
        'wp_name',
        'skip_stock',
    ];

    protected $casts = [
        'skip_stock' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(WpProductMapItem::class);
    }
}
