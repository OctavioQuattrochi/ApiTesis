<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    const STATUSES = [
        'pendiente',
        'esperando_confirmacion',
        'pendiente_pago',
        'pagado',
        'en_produccion',
        'listo_para_entregar',
        'entregado',
        'cancelado',
    ];

    protected $fillable = [
        'user_id',
        'length_cm',
        'height_cm',
        'width_cm',
        'color',
        'image',
        'quantity',
        'estimated_price',
        'raw_response',
        'breakdown',
        'note',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
