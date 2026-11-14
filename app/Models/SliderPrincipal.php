<?php

namespace App\Models;

use Database\Factories\SliderPrincipalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SliderPrincipal extends Model
{
    /** @use HasFactory<SliderPrincipalFactory> */
    use HasFactory;

    protected $table = 'slider_principals';

    protected $fillable = [
        'nombre',
        'link',
        'img_computadora',
        'img_movil',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /**
     * Scope para filtrar sliders activos.
     *
     * @param  Builder<SliderPrincipal>  $query
     * @return Builder<SliderPrincipal>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para ordenar sliders por el campo orden.
     *
     * @param  Builder<SliderPrincipal>  $query
     * @return Builder<SliderPrincipal>
     */
    public function scopeOrdenados(Builder $query): Builder
    {
        return $query->orderBy('orden', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Obtener la URL pública de la imagen de computadora.
     */
    public function getImgComputadoraUrlAttribute(): string
    {
        return $this->resolveImageUrl($this->img_computadora);
    }

    /**
     * Obtener la URL pública de la imagen móvil.
     */
    public function getImgMovilUrlAttribute(): string
    {
        if (empty($this->img_movil)) {
            return $this->img_computadora_url;
        }

        return $this->resolveImageUrl($this->img_movil);
    }

    /**
     * Resuelve la ruta relativa o absoluta de una imagen.
     */
    protected function resolveImageUrl(?string $path): string
    {
        if (empty($path)) {
            return asset('assets/imagen/default.jpg');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'assets/')) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }
}
