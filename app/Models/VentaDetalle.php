<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaDetalle extends Model
{
    use HasFactory;

    protected $table = 'venta_detalles';

    protected $fillable = [
        'venta_id',
        'variacion_id',
        'unidad_medida_id',
        'cantidad',
        'factor_conversion',
        'cantidad_base',
        'precio_unitario',
        'descuento_aplicado',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'factor_conversion' => 'integer',
        'cantidad_base' => 'integer',
        'precio_unitario' => 'decimal:2',
        'descuento_aplicado' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Get the sale that owns this detail line.
     *
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
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
