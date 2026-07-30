<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventario extends Model
{
    use HasFactory;

    protected $fillable = [
        'almacen_id',
        'variacion_id',
        'stock_base',
        'stock_minimo',
    ];

    protected $casts = [
        'stock_base' => 'integer',
        'stock_minimo' => 'integer',
    ];

    /**
     * Get the warehouse/almacén that owns the inventory.
     *
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * Get the product variation associated with this inventory.
     *
     * @return BelongsTo<Variacion, $this>
     */
    public function variacion(): BelongsTo
    {
        return $this->belongsTo(Variacion::class);
    }
}
