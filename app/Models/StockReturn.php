<?php

namespace App\Models;

use App\Traits\HasReferenceId;
use Illuminate\Database\Eloquent\Model;

class StockReturn extends Model
{
    use HasReferenceId;

    protected $guarded = [];

    protected $appends = ['reference_id', 'total_qty'];

    public function items()
    {
        return $this->hasMany(StockReturnItem::class);
    }

    public function getReferenceIdAttribute()
    {
        return $this->generateReferenceId($this->type === 'rto' ? 'RTO' : 'RET');
    }

    public function getTotalQtyAttribute()
    {
        return (int) $this->items->sum('quantity');
    }
}
