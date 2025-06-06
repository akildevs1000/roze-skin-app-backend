<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodPlan extends Model
{
    use HasFactory;

    protected $guarded = [];

    const No = 0;
    const Yes = 1;
}
