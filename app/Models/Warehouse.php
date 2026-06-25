<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $guarded = [];

    protected $appends = ['display_name'];

    /** Single-line address used in the PO "Deliver To" block. */
    public function getDisplayNameAttribute()
    {
        return $this->name;
    }
}
