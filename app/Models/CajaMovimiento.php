<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaMovimiento extends Model
{
    use HasFactory;

    protected $table = 'caja_movimientos';

    protected $fillable = [
        'caja_sesion_id',
        'user_id',
        'tipo',
        'monto',
        'motivo',
        'comprobante',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    /**
     * Sesión de caja asociada.
     *
     * @return BelongsTo<CajaSesion, $this>
     */
    public function cajaSesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class);
    }

    /**
     * Usuario que registró el movimiento.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
