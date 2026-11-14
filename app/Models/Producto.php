<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Producto extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'tipo_producto_id',
        'marca_id',
        'categoria_id',
        'nombre',
        'slug',
        'descripcion',
        'activo',
        'especificaciones',
        'politica_garantia',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_by = auth()->id();
            $model->updated_by = auth()->id();
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });

        static::deleting(function ($model) {
            if (in_array(SoftDeletes::class, class_uses_recursive($model))) {
                if (! $model->isForceDeleting()) {
                    $model->deleted_by = auth()->id();
                    $model->saveQuietly();
                }
            }
        });
    }

    protected $casts = [
        'activo' => 'boolean',
        'especificaciones' => 'array',
    ];

    /**
     * Register media collections for this model.
     * - imagen_principal: a single cover image shown in catalog and product detail.
     * - galeria: up to 10 images for the product gallery carousel.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('imagen_principal')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('galeria')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Register media conversions (auto-generated thumbnails).
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(5)
            ->nonQueued();

        $this->addMediaConversion('card')
            ->width(800)
            ->height(600)
            ->sharpen(5)
            ->nonQueued();
    }

    /**
     * Get the type of product.
     *
     * @return BelongsTo<TipoProducto, $this>
     */
    public function tipoProducto(): BelongsTo
    {
        return $this->belongsTo(TipoProducto::class);
    }

    /**
     * Get the brand of the product.
     *
     * @return BelongsTo<Marca, $this>
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    /**
     * Get the category of the product.
     *
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Get the variations for the product.
     *
     * @return HasMany<Variacion, $this>
     */
    public function variacions(): HasMany
    {
        return $this->hasMany(Variacion::class);
    }

    /**
     * Get the packaging/units of measure configurations for this product.
     *
     * @return HasMany<ProductoEmpaque, $this>
     */
    public function empaques(): HasMany
    {
        return $this->hasMany(ProductoEmpaque::class);
    }

    public function favoritosPor(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'favoritos')->withTimestamps();
    }

    /**
     * Get the discounts associated with the product.
     *
     * @return BelongsToMany<Descuento, $this>
     */
    public function descuentos(): BelongsToMany
    {
        return $this->belongsToMany(Descuento::class, 'producto_descuentos');
    }

    /**
     * URL de la imagen principal del producto con fallback.
     */
    public function getImagenUrlAttribute(): string
    {
        if ($this->hasMedia('imagen_principal')) {
            return $this->getFirstMediaUrl('imagen_principal', 'card') ?: $this->getFirstMediaUrl('imagen_principal');
        }

        if ($this->hasMedia('galeria')) {
            return $this->getFirstMediaUrl('galeria', 'card') ?: $this->getFirstMediaUrl('galeria');
        }

        return asset('assets/imagen/default.jpg');
    }

    /**
     * Precio base referencial (según lista de precio del cliente o precio menor).
     */
    public function getPrecioBaseAttribute(): float
    {
        $nombreListaPrecio = 'Precio Menor';
        if (auth()->check() && auth()->user()->cliente && auth()->user()->cliente->tipo_cliente === 'mayorista') {
            $nombreListaPrecio = auth()->user()->cliente->listaPrecio->nombre ?? 'Precio Mayor';
        }

        $firstVariation = $this->variacions->first();
        if (! $firstVariation) {
            return 0.00;
        }

        if ($firstVariation->relationLoaded('precios')) {
            $precioItem = $firstVariation->precios->first(function ($p) use ($nombreListaPrecio) {
                return str_contains(strtolower($p->listaPrecio?->nombre ?? ''), strtolower($nombreListaPrecio));
            }) ?? $firstVariation->precios->first();

            return (float) ($precioItem?->precio ?? 0.00);
        }

        $precio = $firstVariation->precios()
            ->whereHas('listaPrecio', fn ($q) => $q->where('nombre', 'like', '%'.$nombreListaPrecio.'%'))
            ->value('precio') ?? $firstVariation->precios()->value('precio');

        return (float) ($precio ?? 0.00);
    }

    /**
     * Descuento vigente si aplica.
     */
    public function getDescuentoActivoAttribute(): ?Descuento
    {
        if ($this->relationLoaded('descuentos')) {
            return $this->descuentos->first(function ($d) {
                return $d->activo &&
                    (! $d->fecha_inicio || $d->fecha_inicio <= now()) &&
                    (! $d->fecha_fin || $d->fecha_fin >= now());
            });
        }

        return $this->descuentos()
            ->where('activo', true)
            ->where(fn ($q) => $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now()))
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now()))
            ->first();
    }

    public function getTieneDescuentoAttribute(): bool
    {
        return $this->descuento_activo !== null;
    }

    public function getPorcentajeDescuentoAttribute(): int
    {
        return $this->descuento_activo ? (int) $this->descuento_activo->porcentaje_descuento : 0;
    }

    public function getPrecioFinalAttribute(): float
    {
        $base = $this->precio_base;
        if ($this->tiene_descuento) {
            return round($base * (1 - $this->porcentaje_descuento / 100), 2);
        }

        return $base;
    }

    /**
     * Extrae colores disponibles de las variaciones.
     *
     * @return array<int, array{nombre: string, hex: ?string}>
     */
    public function getColoresDisponiblesAttribute(): array
    {
        $colors = [];
        foreach ($this->variacions as $v) {
            if (! $v->relationLoaded('valores')) {
                continue;
            }
            foreach ($v->valores as $val) {
                if ($val->relationLoaded('atributo') && str_contains(strtolower($val->atributo->nombre ?? ''), 'color')) {
                    $colors[$val->valor] = [
                        'nombre' => $val->valor,
                        'hex' => $val->codigo_color_hex,
                    ];
                }
            }
        }

        return array_values($colors);
    }
}
