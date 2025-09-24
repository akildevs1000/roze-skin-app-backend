<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function inventory_item()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    protected $appends = ['stock_date_display'];

    public function getStockDateDisplayAttribute()
    {
        return date("d-M-y", strtotime($this->stock_date));
    }


}
