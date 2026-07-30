<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasFactory;

    protected $fillable = [
        'categoria_padre_id',
        'codigo',
        'nombre',
        'slug',
        'descripcion',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /**
     * Get the parent category.
     *
     * @return BelongsTo<Categoria, $this>
     */
    public function categoriaPadre(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_padre_id');
    }

    /**
     * Get the subcategories.
     *
     * @return HasMany<Categoria, $this>
     */
    public function subcategorias(): HasMany
    {
        return $this->hasMany(Categoria::class, 'categoria_padre_id')->orderBy('orden');
    }

    /**
     * Get the products in this category.
     *
     * @return HasMany<Producto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }
}
