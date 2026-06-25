<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class PurchaseOrderAttachment extends Model
{
    protected $guarded = [];

    protected $appends = ['url'];

    public function purchase_order()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** Absolute URL to the stored file (served from public/). */
    public function getUrlAttribute()
    {
        return $this->path ? URL::to($this->path) : null;
    }
}
