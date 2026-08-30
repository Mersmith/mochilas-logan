<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CajaSesion extends Model
{
    use HasFactory;

    protected $table = 'caja_sesions';

    protected $fillable = [
        'caja_id',
        'user_id',
        'monto_inicial',
        'fecha_apertura',
        'fecha_cierre',
        'monto_cierre_efectivo',
        'monto_cierre_esperado',
        'diferencia_efectivo',
        'total_ventas_efectivo',
        'total_ventas_tarjeta',
        'total_ventas_transferencia',
        'total_ventas_yape_plin',
        'total_ingresos_extra',
        'total_egresos_gastos',
        'estado',
        'observaciones_apertura',
        'observaciones_cierre',
    ];

    protected $casts = [
        'monto_inicial' => 'decimal:2',
        'monto_cierre_efectivo' => 'decimal:2',
        'monto_cierre_esperado' => 'decimal:2',
        'diferencia_efectivo' => 'decimal:2',
        'total_ventas_efectivo' => 'decimal:2',
        'total_ventas_tarjeta' => 'decimal:2',
        'total_ventas_transferencia' => 'decimal:2',
        'total_ventas_yape_plin' => 'decimal:2',
        'total_ingresos_extra' => 'decimal:2',
        'total_egresos_gastos' => 'decimal:2',
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
    ];

    /**
     * Caja física a la que pertenece la sesión.
     *
     * @return BelongsTo<Caja, $this>
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /**
     * Cajero/Usuario responsable.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ventas procesadas durante este turno de caja.
     *
     * @return HasMany<Venta, $this>
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'caja_sesion_id');
    }

    /**
     * Movimientos de caja chica (ingresos y gastos).
     *
     * @return HasMany<CajaMovimiento, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class, 'caja_sesion_id');
    }

    /**
     * Total general de ventas de todos los métodos de pago.
     */
    public function getTotalVentasAttribute(): float
    {
        return (float) ($this->total_ventas_efectivo + $this->total_ventas_tarjeta + $this->total_ventas_transferencia + $this->total_ventas_yape_plin);
    }

    /**
     * Calcula y actualiza los totales en tiempo real.
     */
    public function recalcularTotales(): self
    {
        $this->total_ventas_efectivo = (float) $this->ventas()->where('estado_pago', 'pagado')->where('metodo_pago', 'efectivo')->sum('total');
        $this->total_ventas_tarjeta = (float) $this->ventas()->where('estado_pago', 'pagado')->where('metodo_pago', 'tarjeta')->sum('total');
        $this->total_ventas_transferencia = (float) $this->ventas()->where('estado_pago', 'pagado')->where('metodo_pago', 'transferencia')->sum('total');
        $this->total_ventas_yape_plin = (float) $this->ventas()->where('estado_pago', 'pagado')->where('metodo_pago', 'yape_plin')->sum('total');

        $this->total_ingresos_extra = (float) $this->movimientos()->where('tipo', 'ingreso')->sum('monto');
        $this->total_egresos_gastos = (float) $this->movimientos()->where('tipo', 'egreso')->sum('monto');

        $this->monto_cierre_esperado = round((float) $this->monto_inicial + (float) $this->total_ventas_efectivo + (float) $this->total_ingresos_extra - (float) $this->total_egresos_gastos, 2);

        $this->save();

        return $this;
    }
}
