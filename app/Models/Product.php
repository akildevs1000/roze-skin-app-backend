<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the product_category that owns the Product
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product_category()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    protected $appends = ['date_time', 'product_with_item_name','display_image'];

    public function getProductWithItemNameAttribute()
    {
        return $this->description . " " . "({$this->item_number})";
    }

    public function getDisplayImageAttribute()
    {
        if (!$this->image) {
            return null;
        }

        return URL::to($this->image); // returns full URL like http://yourdomain.com/products/filename.webp
    }

    public function getDateTimeAttribute()
    {
        return date("d-M-y h:i:sa", strtotime($this->created_at));
    }
}
