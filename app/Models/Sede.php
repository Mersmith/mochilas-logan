<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sede extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'direccion',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'activo' => 'boolean',
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
     * Get the almacenes for this sede.
     *
     * @return HasMany<Almacen, $this>
     */
    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    /**
     * Get the series associated with this Sede.
     *
     * @return HasMany<Serie, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }
}
