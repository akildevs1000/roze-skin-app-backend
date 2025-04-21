<?php

namespace App\Models;

use App\Models\PaymentMode;
use App\Traits\HasReferenceId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasReferenceId;

    protected $guarded = [];

    protected $appends = ['date_time', 'reference_id'];

    public function getReferenceIdAttribute()
    {
        return $this->generateReferenceId("P");
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
