<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cupons';

    protected $fillable = [
        'codigo',
        'tipo_descuento',
        'valor_descuento',
        'monto_minimo_compra',
        'usos_totales',
        'usos_restantes',
        'fecha_inicio',
        'fecha_expiracion',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'valor_descuento' => 'decimal:2',
        'monto_minimo_compra' => 'decimal:2',
        'usos_totales' => 'integer',
        'usos_restantes' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_expiracion' => 'date',
        'activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function (Model $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (Model $model) {
            if (auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    /**
     * Get the user that created the record.
     *
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
