<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caja extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cajas';

    protected $fillable = [
        'sede_id',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Sede a la que pertenece la caja.
     *
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Historial de sesiones / turnos de esta caja.
     *
     * @return HasMany<CajaSesion, $this>
     */
    public function sesiones(): HasMany
    {
        return $this->hasMany(CajaSesion::class);
    }

    /**
     * Sesión actualmente abierta.
     *
     * @return HasOne<CajaSesion, $this>
     */
    public function sesionActiva(): HasOne
    {
        return $this->hasOne(CajaSesion::class)->where('estado', 'abierta')->latestOfMany();
    }
}
