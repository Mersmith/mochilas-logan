<?php

namespace App\Models;

use Database\Factories\PaisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property string $codigo_iso2
 * @property string $codigo_iso3
 * @property string|null $prefijo_telefono
 * @property string $label_nivel1
 * @property string $label_nivel2
 * @property string $label_nivel3
 * @property string|null $simbolo_moneda
 * @property string|null $codigo_moneda
 * @property bool $activo
 */
class Pais extends Model
{
    /** @use HasFactory<PaisFactory> */
    use HasFactory;

    protected $table = 'paises';

    protected $fillable = [
        'nombre',
        'codigo_iso2',
        'codigo_iso3',
        'prefijo_telefono',
        'label_nivel1',
        'label_nivel2',
        'label_nivel3',
        'simbolo_moneda',
        'codigo_moneda',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /**
     * Todos los ubigeos (departamentos, provincias, distritos) de este país.
     *
     * @return HasMany<Ubigeo, $this>
     */
    public function ubigeos(): HasMany
    {
        return $this->hasMany(Ubigeo::class);
    }

    /**
     * Solo los ubigeos de nivel 1 (Departamentos / Regiones / Provincias).
     *
     * @return HasMany<Ubigeo, $this>
     */
    public function departamentos(): HasMany
    {
        return $this->hasMany(Ubigeo::class)->where('nivel', 1)->orderBy('nombre');
    }

    /**
     * Direcciones registradas en este país.
     *
     * @return HasMany<Direccion, $this>
     */
    public function direcciones(): HasMany
    {
        return $this->hasMany(Direccion::class);
    }
}
