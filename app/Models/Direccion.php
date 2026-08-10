<?php

namespace App\Models;

use Database\Factories\DireccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $cliente_id
 * @property int|null $pais_id
 * @property int|null $departamento_id
 * @property int|null $provincia_id
 * @property int|null $distrito_id
 * @property string $alias
 * @property string|null $destinatario
 * @property string|null $telefono_contacto
 * @property string $direccion
 * @property string|null $referencia
 * @property string|null $codigo_postal
 * @property bool $es_predeterminada
 * @property bool $activo
 */
class Direccion extends Model
{
    /** @use HasFactory<DireccionFactory> */
    use HasFactory;

    protected $table = 'direcciones';

    protected $fillable = [
        'cliente_id',
        'pais_id',
        'departamento_id',
        'provincia_id',
        'distrito_id',
        'alias',
        'destinatario',
        'telefono_contacto',
        'direccion',
        'referencia',
        'codigo_postal',
        'es_predeterminada',
        'activo',
    ];

    protected $casts = [
        'es_predeterminada' => 'boolean',
        'activo' => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /**
     * El cliente dueño de esta dirección.
     *
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * País de la dirección.
     *
     * @return BelongsTo<Pais, $this>
     */
    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    /**
     * Nivel 1: Departamento / Región / Provincia (según país).
     *
     * @return BelongsTo<Ubigeo, $this>
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class, 'departamento_id');
    }

    /**
     * Nivel 2: Provincia / Municipio / Cantón (según país).
     *
     * @return BelongsTo<Ubigeo, $this>
     */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class, 'provincia_id');
    }

    /**
     * Nivel 3: Distrito / Municipio / Localidad (según país).
     *
     * @return BelongsTo<Ubigeo, $this>
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class, 'distrito_id');
    }

    /**
     * Ventas que usaron esta dirección como referencia de envío.
     *
     * @return HasMany<Venta, $this>
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    // ─── Booted ───────────────────────────────────────────────────────────────

    /**
     * Cuando se marca una dirección como predeterminada,
     * las demás del mismo cliente se desmarcan automáticamente.
     */
    protected static function booted(): void
    {
        static::saving(function (Direccion $direccion) {
            if ($direccion->es_predeterminada) {
                static::where('cliente_id', $direccion->cliente_id)
                    ->where('id', '!=', $direccion->id ?? 0)
                    ->update(['es_predeterminada' => false]);
            }
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Dirección completa formateada usando los nombres del ubigeo.
     * Usa los campos de texto del snapshot si no hay ubigeo cargado.
     */
    public function direccionCompleta(): string
    {
        $partes = array_filter([
            $this->direccion,
            $this->distrito?->nombre,
            $this->provincia?->nombre,
            $this->departamento?->nombre,
            $this->pais?->nombre,
        ]);

        return implode(', ', $partes);
    }

    /**
     * Etiquetas geográficas según el país seleccionado.
     *
     * @return array{nivel1: string, nivel2: string, nivel3: string}
     */
    public function labelsGeograficos(): array
    {
        return [
            'nivel1' => $this->pais?->label_nivel1 ?? 'Departamento',
            'nivel2' => $this->pais?->label_nivel2 ?? 'Provincia',
            'nivel3' => $this->pais?->label_nivel3 ?? 'Distrito',
        ];
    }
}
