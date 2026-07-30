<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cupon extends Model
{
    use HasFactory;

    protected $table = 'cupons';

    protected $fillable = [
        'codigo',
        'tipo_descuento',
        'valor_descuento',
        'monto_minimo_compra',
        'usos_totales',
        'usos_restantes',
        'fecha_inicio',
        'fecha_expiracion',
        'activo',
    ];

    protected $casts = [
        'valor_descuento' => 'decimal:2',
        'monto_minimo_compra' => 'decimal:2',
        'usos_totales' => 'integer',
        'usos_restantes' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_expiracion' => 'date',
        'activo' => 'boolean',
    ];
}
