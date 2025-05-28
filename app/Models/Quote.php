<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $fillable = [
        'length_cm',
        'height_cm',
        'width_cm',
        'color',
        'quantity',
        'estimated_price',
        'raw_response',
    ];
}
