<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingAddress extends Model
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
            // $this->address_2,
            $this->city ?? "---",
            // $this->state ?? "---",
            $this->postcode ?? "000000",
            $this->country
        ]);

        return implode(', ', $parts);
    }
}
