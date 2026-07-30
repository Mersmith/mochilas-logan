<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'direccion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Get the almacenes for this sede.
     *
     * @return HasMany<Almacen, $this>
     */
    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    /**
     * Get the series associated with this Sede.
     *
     * @return HasMany<Serie, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }
}
