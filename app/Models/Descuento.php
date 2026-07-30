<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Descuento extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'porcentaje_descuento',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected $casts = [
        'porcentaje_descuento' => 'integer',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'activo' => 'boolean',
    ];

    /**
     * Get the products that have this discount.
     *
     * @return BelongsToMany<Producto, $this>
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_descuentos');
    }
}
