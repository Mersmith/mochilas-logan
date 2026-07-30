<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Serie extends Model
{
    use HasFactory;

    protected $fillable = [
        'sede_id',
        'tipo_documento_id',
        'serie',
        'correlativo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'correlativo' => 'integer',
    ];

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
