<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'color',
        'quantity',
        'status',
        'created_by',
    ];

    // Relación con el producto
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Relación con el usuario creador
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}