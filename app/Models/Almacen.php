<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Almacen extends Model
{
    use HasFactory;

    protected $table = 'almacens';

    protected $fillable = [
        'sede_id',
        'nombre',
        'ubicacion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Get the sede that owns the almacén.
     *
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Get the inventarios in this almacén.
     *
     * @return HasMany<Inventario, $this>
     */
    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }
}
