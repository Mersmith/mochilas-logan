<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $tipo_persona natural|juridica
 * @property string $tipo_cliente minorista|mayorista|emprendedor
 * @property int $lista_precio_id
 * @property string|null $dni
 * @property string|null $ruc
 * @property string|null $razon_social
 * @property string|null $telefono
 * @property bool $activo
 */
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tipo_persona',
        'tipo_cliente',
        'lista_precio_id',
        'dni',
        'ruc',
        'razon_social',
        'telefono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /**
     * El usuario autenticado dueño de este perfil.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Lista de precios asignada según tipo de cliente.
     *
     * @return BelongsTo<ListaPrecio, $this>
     */
    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class);
    }

    /**
     * Todas las direcciones guardadas del cliente.
     *
     * @return HasMany<Direccion, $this>
     */
    public function direcciones(): HasMany
    {
        return $this->hasMany(Direccion::class);
    }

    /**
     * Dirección predeterminada del cliente.
     *
     * @return HasOne<Direccion, $this>
     */
    public function direccionPredeterminada(): HasOne
    {
        return $this->hasOne(Direccion::class)->where('es_predeterminada', true);
    }

    /**
     * Ventas realizadas por este cliente.
     *
     * @return HasMany<Venta, $this>
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'user_id', 'user_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<Cliente>  $query
     * @return Builder<Cliente>
     */
    public function scopeMinoristas(Builder $query): Builder
    {
        return $query->where('tipo_cliente', 'minorista');
    }

    /**
     * @param  Builder<Cliente>  $query
     * @return Builder<Cliente>
     */
    public function scopeMayoristas(Builder $query): Builder
    {
        return $query->where('tipo_cliente', 'mayorista');
    }

    /**
     * @param  Builder<Cliente>  $query
     * @return Builder<Cliente>
     */
    public function scopeEmprendedores(Builder $query): Builder
    {
        return $query->where('tipo_cliente', 'emprendedor');
    }

    /**
     * @param  Builder<Cliente>  $query
     * @return Builder<Cliente>
     */
    public function scopePersonasNaturales(Builder $query): Builder
    {
        return $query->where('tipo_persona', 'natural');
    }

    /**
     * @param  Builder<Cliente>  $query
     * @return Builder<Cliente>
     */
    public function scopeEmpresas(Builder $query): Builder
    {
        return $query->where('tipo_persona', 'juridica');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Nombre para mostrar: razón social si es empresa, nombre del user si es persona natural.
     */
    public function nombreMostrar(): string
    {
        return $this->razon_social ?? $this->user->name;
    }

    /**
     * Documento de identificación principal: RUC preferido sobre DNI.
     */
    public function documentoIdentificacion(): ?string
    {
        return $this->ruc ?? $this->dni;
    }

    /**
     * Indica si el cliente es una empresa (persona jurídica).
     */
    public function esEmpresa(): bool
    {
        return $this->tipo_persona === 'juridica';
    }

    /**
     * Indica si el cliente es persona natural.
     */
    public function esPersonaNatural(): bool
    {
        return $this->tipo_persona === 'natural';
    }

    public function mediosPagos(): HasMany
    {
        return $this->hasMany(MedioPago::class);
    }

    public function datoReembolso()
    {
        return $this->hasOne(DatoReembolso::class);
    }

    public function favoritos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'favoritos')->withTimestamps();
    }
}
