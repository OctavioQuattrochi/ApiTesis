<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'type',
        'name',
        'image',
        'color',
        'quantity',
        'location',
        'material',
        'supplier',
        'cost',
        'final_price'
    ];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    // Accessor para la URL de la imagen
    public function getImageUrlAttribute()
    {
        // Si la imagen está en el frontend, devolvé la ruta pública
        return $this->image
            ? "http://localhost:5173/src/sources/store/{$this->image}"
            : null;
    }

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

