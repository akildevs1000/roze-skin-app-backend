<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappClient extends Model
{
    use HasFactory;

    protected $fillable = ['accounts'];

    protected $casts = [
        'accounts' => 'array', // Automatically cast JSON to array
    ];
}
