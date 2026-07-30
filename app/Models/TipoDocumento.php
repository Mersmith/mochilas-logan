<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDocumento extends Model
{
    use HasFactory;

    protected $table = 'tipo_documentos';

    protected $fillable = [
        'nombre',
        'codigo_sunat',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Get the series for this document type.
     *
     * @return HasMany<Serie, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }
}
