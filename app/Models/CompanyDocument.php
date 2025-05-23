<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyDocument extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        "created_at" => "datetime:d-M-y"
    ];

    public function getPathAttribute($value)
    {
        if (!$value) {
            return null;
        }
        return asset('company_document/' . $value);
    }
}
