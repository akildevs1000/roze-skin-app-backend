<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        "customer_id",
        "user_id",
        "username",
        "email",
        "order_id",
        "order_date",
        "order_status",
        "currency",
        "total",
        "payment_method",
        "payment_method_title",
        "shipping_method",
        "items",

        "business_source_id",
        "delivery_service_id",
        "tracking_number",

        "paid_amount"
    ];

    protected $with = [
        'customer.shipping_address',
        'customer.billing_address',
    ];

    protected $casts = [
        "items" => "array"
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function business_source()
    {
        return $this->belongsTo(BusinessSource::class)
            ->withDefault(
                ["name" => "---"]
            );
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function delivery_service()
    {
        return $this->belongsTo(DeliveryService::class)
            ->withDefault(
                ["name" => "---"]
            );
    }

    protected $appends = ['date_time'];

    public function getDateTimeAttribute()
    {
        return date("d-M-y h:i:sa", strtotime($this->order_date));
    }
}
