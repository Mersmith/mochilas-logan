<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoDescuento extends Model
{
    use HasFactory;

    protected $fillable = [
        'producto_id',
        'descuento_id',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function descuento(): BelongsTo
    {
        return $this->belongsTo(Descuento::class);
    }
}
