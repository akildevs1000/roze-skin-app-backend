<?php

namespace App\Models;

use App\Traits\HasReferenceId;
use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    use HasReferenceId;

    protected $guarded = [];

    protected $appends = ['reference_id'];

    public function product()
    {
        return $this->belongsTo(InventoryItem::class, 'product_id');
    }

    public function getReferenceIdAttribute()
    {
        return $this->generateReferenceId('ADJ');
    }
}
