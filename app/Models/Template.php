<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;

    protected $casts = [
        "created_at" => "datetime:d-M-y",
        "updated_at" => "datetime:d-M-y",
    ];

    protected $guarded = [];
    
    const ORDER_RECEIVED = 1;
    const ORDER_DISPATCHED = 2;
    const BIRTHDAY_WISH = 3;
    const UNKNOWN = 0;
    
    const TEMPLATE_TYPES = [
        1 => "order_received",
        2 => "order_dispatched",
        3 => "birthday_wish",
        0 => "unknown",
    ];

    protected $appends = ['action'];

    public function getActionAttribute()
    {
        return self::TEMPLATE_TYPES[$this->action_id];
    }

    const validateFields = [
        "name" => "required|max:50",
        "salutation" => "required|max:100",
        "body" => "required|max:1000",
        "attachment" => "nullable",
        "action_id" => "required",
        "medium" => "nullable"
    ];
}
