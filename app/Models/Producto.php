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

    /**
     * Get the discounts associated with the product.
     *
     * @return BelongsToMany<Descuento, $this>
     */
    public function descuentos(): BelongsToMany
    {
        return $this->belongsToMany(Descuento::class, 'producto_descuentos');
    }
}
