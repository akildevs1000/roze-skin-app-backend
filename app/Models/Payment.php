<?php

namespace App\Models;

use App\Models\PaymentMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['date_time', 'payment_reference_id'];

    public function getPaymentReferenceIdAttribute()
    {
        return "P-" . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getDateTimeAttribute()
    {
        return date("d-M-y h:i:sa", strtotime($this->created_at));
    }

    public function payment_mode()
    {
        return $this->belongsTo(PaymentMode::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
