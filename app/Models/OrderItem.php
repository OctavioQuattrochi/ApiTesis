<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'variant_id',
        'quote_id',
        'quantity',
        'price_unit',
        'subtotal',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(\App\Models\ProductVariant::class, 'variant_id');
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }
}
