<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingAddress extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = [
        "full_address"
    ];

    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address_1,
            $this->address_2,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country
        ]);

        return implode(', ', $parts);
    }
}
