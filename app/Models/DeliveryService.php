<?php

namespace App\Models;

use App\Models\Scopes\OrderByUpdatedAt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryService extends Model
{
    use HasFactory;
    
    protected $guarded = [];


    protected static function booted()
    {
        static::addGlobalScope(new OrderByUpdatedAt);
    }
}
