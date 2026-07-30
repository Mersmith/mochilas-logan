<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Variacion extends Model
{
    use HasFactory;

    protected $table = 'variacions';

    protected $fillable = [
        'producto_id',
        'sku',
        'codigo_barras',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Get the product that owns this variation.
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Get the attribute values that define this variation.
     *
     * @return BelongsToMany<AtributoValor, $this>
     */
    public function valores(): BelongsToMany
    {
        return $this->belongsToMany(AtributoValor::class, 'variacion_valores', 'variacion_id', 'atributo_valor_id');
    }

    /**
     * Get the prices for this variation across different price lists.
     *
     * @return HasMany<VariacionPrecio, $this>
     */
    public function precios(): HasMany
    {
        return $this->hasMany(VariacionPrecio::class, 'variacion_id');
    }

    /**
     * Get the inventory levels for this variation across different warehouses.
     *
     * @return HasMany<Inventario, $this>
     */
    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class, 'variacion_id');
    }
}
