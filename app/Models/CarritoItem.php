<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarritoItem extends Model
{
    use HasFactory;

    protected $table = 'carrito_items';

    protected $fillable = [
        'carrito_id',
        'variacion_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function carrito(): BelongsTo
    {
        return $this->belongsTo(Carrito::class);
    }

    public function variacion(): BelongsTo
    {
        return $this->belongsTo(Variacion::class);
    }
}
