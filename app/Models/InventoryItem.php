<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class InventoryItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['date_time', 'display_image'];

    public function getDisplayImageAttribute()
    {
        if (! $this->image) {
            return null;
        }

        return URL::to($this->image);
    }

    public function getDateTimeAttribute()
    {
        return date("d-M-y h:i:sa", strtotime($this->created_at));
    }

    public function stockEntries()
    {
        return $this->hasMany(StockEntry::class);
    }

    public function order_items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
