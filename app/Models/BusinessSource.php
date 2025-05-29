<?php

namespace App\Models;

use App\Models\Scopes\OrderByUpdatedAt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessSource extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted()
    {
        // Disable in Controller if Needed in =>  YourModel::withoutGlobalScope(OrderByUpdatedAt::class)->get();
        static::addGlobalScope(new OrderByUpdatedAt);
    }

    protected static function booted_old()
    {
        // in case want to use key to disable on contorller level
        // static::addGlobalScope('orderByUpdatedAt', function (Builder $builder) {
        //     $builder->orderBy('updated_at', 'desc');
        // });

        // example usage in controller to disable
        // YourModel::withoutGlobalScope('orderByUpdatedAt')->get();

    }
}
