<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListaPrecio extends Model
{
    use HasFactory;

    protected $table = 'lista_precios';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Get the prices associated with this list.
     *
     * @return HasMany<VariacionPrecio, $this>
     */
    public function precios(): HasMany
    {
        return $this->hasMany(VariacionPrecio::class);
    }
}
