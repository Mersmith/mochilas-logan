<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Kardex extends Model
{
    use HasFactory;

    protected $table = 'kardex';

    protected $fillable = [
        'almacen_id',
        'variacion_id',
        'tipo_transaccion',
        'concepto',
        'cantidad',
        'stock_anterior',
        'stock_posterior',
        'costo_unitario',
        'costo_total',
        'valor_total_almacen',
        'origen_documento_id',
        'origen_documento_type',
        'creado_por_usuario_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'stock_anterior' => 'integer',
        'stock_posterior' => 'integer',
        'costo_unitario' => 'decimal:2',
        'costo_total' => 'decimal:2',
        'valor_total_almacen' => 'decimal:2',
    ];

    /**
     * Get the warehouse.
     *
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
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
     * Get the parent source document model (GuiaInventario, Venta, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function origenDocumento(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created this transaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_usuario_id');
    }
}
