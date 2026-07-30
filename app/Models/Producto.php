<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tipo_producto_id',
        'marca_id',
        'categoria_id',
        'nombre',
        'slug',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Get the type of product.
     *
     * @return BelongsTo<TipoProducto, $this>
     */
    public function tipoProducto(): BelongsTo
    {
        return $this->belongsTo(TipoProducto::class);
    }

    /**
     * Get the brand of the product.
     *
     * @return BelongsTo<Marca, $this>
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    /**
     * Get the category of the product.
     *
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Get the variations for the product.
     *
     * @return HasMany<Variacion, $this>
     */
    public function variacions(): HasMany
    {
        return $this->hasMany(Variacion::class);
    }

    /**
     * Get the packaging/units of measure configurations for this product.
     *
     * @return HasMany<ProductoEmpaque, $this>
     */
    public function empaques(): HasMany
    {
        return $this->hasMany(ProductoEmpaque::class);
    }

    /**
     * Get the discounts associated with the product.
     *
     * @return BelongsToMany<Descuento, $this>
     */
    public function descuentos(): BelongsToMany
    {
        return $this->belongsToMany(Descuento::class, 'producto_descuentos');
    }
}
