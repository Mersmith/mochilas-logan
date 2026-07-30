<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuiaInventarioDetalle extends Model
{
    use HasFactory;

    protected $table = 'guia_inventario_detalles';

    protected $fillable = [
        'guia_inventario_id',
        'variacion_id',
        'unidad_medida_id',
        'cantidad',
        'factor_conversion',
        'cantidad_base',
        'costo_unitario',
        'costo_total',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'factor_conversion' => 'integer',
        'cantidad_base' => 'integer',
        'costo_unitario' => 'decimal:2',
        'costo_total' => 'decimal:2',
    ];

    /**
     * Get the guide that owns this detail line.
     *
     * @return BelongsTo<GuiaInventario, $this>
     */
    public function guiaInventario(): BelongsTo
    {
        return $this->belongsTo(GuiaInventario::class, 'guia_inventario_id');
    }

    /**
     * Get the product variation.
     *
     * @return BelongsTo<Variacion, $this>
     */
    public function variacion(): BelongsTo
    {
        return $this->belongsTo(Variacion::class);
    }

    /**
     * Get the unit of measure.
     *
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }
}
