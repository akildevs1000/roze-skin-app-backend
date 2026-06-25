<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['display_name', 'full_name'];

    /**
     * Get the vendor_category that owns the Vendor
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vendor_category()
    {
        return $this->belongsTo(VendorCategory::class);
    }

    public function getFullNameAttribute()
    {
        return trim(implode(' ', array_filter([$this->title, $this->first_name, $this->last_name])));
    }

    /** Label used in vendor selects / PO & GRN listings. */
    public function getDisplayNameAttribute()
    {
        return $this->company_name ?: ($this->full_name ?: $this->attributes['name'] ?? null);
    }
}
