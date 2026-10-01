<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        "order_id",
        "invoice_id",
        "customer_id",
        "customer_name",
        "phone",
        "order_value",
        "refund_value",
        "reason",
        "restocked",
        "restocked_units",
    ];

    protected $casts = [
        "order_value"  => "decimal:2",
        "refund_value" => "decimal:2",
        "restocked"    => "boolean",
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
