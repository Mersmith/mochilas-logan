<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'razon_social',
        'ruc',
        'direccion',
        'contacto_nombre',
        'contacto_celular',
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
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model))) {
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
     * Get the inventory guides associated with this provider.
     *
     * @return HasMany<GuiaInventario, $this>
     */
    public function guias(): HasMany
    {
        return $this->hasMany(GuiaInventario::class, 'proveedor_id');
    }
}
