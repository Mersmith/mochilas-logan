<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedioPago extends Model
{
    protected $fillable = [
        'cliente_id',
        'tipo',
        'proveedor',
        'ultimos_cuatro',
        'fecha_expiracion',
        'es_predeterminado',
    ];

    protected $casts = [
        'es_predeterminado' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    protected static function booted(): void
    {
        static::saving(function (MedioPago $medioPago) {
            if ($medioPago->es_predeterminado) {
                static::where('cliente_id', $medioPago->cliente_id)
                    ->where('id', '!=', $medioPago->id ?? 0)
                    ->update(['es_predeterminado' => false]);
            }
        });
    }
}
