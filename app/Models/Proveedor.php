<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'razon_social',
        'ruc',
        'direccion',
        'contacto_nombre',
        'contacto_celular',
        'activo',
    ];

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
