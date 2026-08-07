<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Serie extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sede_id',
        'tipo_documento_id',
        'serie',
        'correlativo',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'correlativo' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->created_by = auth()->id();
            $model->updated_by = auth()->id();
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });

        static::deleting(function ($model) {
            $model->deleted_by = auth()->id();
            $model->saveQuietly();
        });
    }

    /**
     * Get the Sede that owns the Serie.
     *
     * @return BelongsTo<Sede, $this>
     */
    public function Sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Get the TipoDocumento that owns the Serie.
     *
     * @return BelongsTo<TipoDocumento, $this>
     */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }
}
