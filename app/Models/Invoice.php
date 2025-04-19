<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        "created_at" => "datetime:d-M-y",
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function delivery_service()
    {
        return $this->belongsTo(DeliveryService::class);
    }

    public function business_source()
    {
        return $this->belongsTo(BusinessSource::class);
    }

    /**
     * Get all of the comments for the Invoice
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    protected $appends = ['date_time', 'invoice_reference_id'];

    public function getInvoiceReferenceIdAttribute()
    {
        return "INV-" . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getDateTimeAttribute()
    {
        return date("d-M-y h:i:sa", strtotime($this->created_at));
    }
}
