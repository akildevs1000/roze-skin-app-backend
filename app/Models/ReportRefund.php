<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportRefund extends Model
{
    protected $fillable = ["period", "amount", "note"];

    protected $casts = ["amount" => "decimal:2"];
}
