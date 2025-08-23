<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $fillable = [
        'user_id',
        'length_cm',
        'height_cm',
        'width_cm',
        'color',
        'quantity',
        'estimated_price',
        'raw_response',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
