<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class InventoryItem extends Model
{
    protected $guarded = [];

    protected $appends = ['display_image'];

    public function stock()
    {
        return $this->hasOne(InventoryStock::class, 'product_id');
    }

    public function getDisplayImageAttribute()
    {
        return $this->image ? URL::to($this->image) : null;
    }
}
