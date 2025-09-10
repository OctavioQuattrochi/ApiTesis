<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'variant_id',
        'quote_id',
        'quantity',
        'price_unit',
        'subtotal'
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
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
