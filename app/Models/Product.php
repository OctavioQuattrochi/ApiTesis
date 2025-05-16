<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'type',
        'name',
        'color',
        'quantity',
        'location',
        'material',
        'supplier',
        'cost',
        'final_price'
    ];

    // Auto-calcular el precio final si es materia prima
    protected static function booted()
    {
        static::creating(function ($product) {
            if ($product->type === 'raw_material' && $product->cost) {
                $product->final_price = $product->cost * 1.5;
            }
        });

        static::updating(function ($product) {
            if ($product->type === 'raw_material' && $product->cost) {
                $product->final_price = $product->cost * 1.5;
            }
        });
    }
}

