<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminExpenseAttachment extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getAttachmentAttribute($val)
    {
        return asset("expense-uploads/" . $this->admin_expense_id . "/" . $val);
    }
}
