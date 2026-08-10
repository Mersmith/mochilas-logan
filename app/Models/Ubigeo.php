<?php

namespace App\Models;

use Database\Factories\UbigeoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $pais_id
 * @property int|null $parent_id
 * @property int $nivel 1=Departamento, 2=Provincia, 3=Distrito
 * @property string $nombre
 * @property string|null $codigo
 */
class Ubigeo extends Model
{
    /** @use HasFactory<UbigeoFactory> */
    use HasFactory;

    protected $fillable = [
        'pais_id',
        'parent_id',
        'nivel',
        'nombre',
        'codigo',
    ];

    protected $casts = [
        'nivel' => 'integer',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /**
     * País al que pertenece este ubigeo.
     *
     * @return BelongsTo<Pais, $this>
     */
    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    /**
     * Ubigeo padre (ej: la provincia de un distrito, el departamento de una provincia).
     *
     * @return BelongsTo<Ubigeo, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class, 'parent_id');
    }

    /**
     * Ubigeos hijos directos (ej: provincias de un departamento, distritos de una provincia).
     *
     * @return HasMany<Ubigeo, $this>
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(Ubigeo::class, 'parent_id')->orderBy('nombre');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<Ubigeo>  $query
     * @return Builder<Ubigeo>
     */
    public function scopeDepartamentos(Builder $query): Builder
    {
        return $query->where('nivel', 1)->orderBy('nombre');
    }

    /**
     * @param  Builder<Ubigeo>  $query
     * @return Builder<Ubigeo>
     */
    public function scopeProvincias(Builder $query): Builder
    {
        return $query->where('nivel', 2)->orderBy('nombre');
    }

    /**
     * @param  Builder<Ubigeo>  $query
     * @return Builder<Ubigeo>
     */
    public function scopeDistritos(Builder $query): Builder
    {
        return $query->where('nivel', 3)->orderBy('nombre');
    }

    /**
     * Provincias que pertenecen a este departamento.
     *
     * @param  Builder<Ubigeo>  $query
     * @return Builder<Ubigeo>
     */
    public function scopeDePartamento(Builder $query, int $departamentoId): Builder
    {
        return $query->where('parent_id', $departamentoId);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Etiqueta del nivel según el país (ej: "Departamento", "Región", "Provincia").
     */
    public function labelNivel(): string
    {
        return match ($this->nivel) {
            1 => $this->pais->label_nivel1,
            2 => $this->pais->label_nivel2,
            3 => $this->pais->label_nivel3,
            default => "Nivel {$this->nivel}",
        };
    }
}
