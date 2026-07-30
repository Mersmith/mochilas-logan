<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariacionPrecio extends Model
{
    use HasFactory;

    protected $table = 'variacion_precios';

    protected $fillable = [
        'variacion_id',
        'lista_precio_id',
        'precio',
        'precio_antiguo',
        'simbolo',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_antiguo' => 'decimal:2',
    ];

    /**
     * Get the variation this price belongs to.
     *
     * @return BelongsTo<Variacion, $this>
     */
    public function variacion(): BelongsTo
    {
        return $this->belongsTo(Variacion::class);
    }

    /**
     * Get the price list this price belongs to.
     *
     * @return BelongsTo<ListaPrecio, $this>
     */
    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class);
    }
}
