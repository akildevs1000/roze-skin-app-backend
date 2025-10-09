<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    public function order()
    {
        return $this->belongsTo(Order::class, "order_id","order_id");
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

     public function orders()
    {
        return $this->hasMany(Order::class, "order_id","order_id");
    }
}
