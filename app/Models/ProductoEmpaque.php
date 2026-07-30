<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoEmpaque extends Model
{
    use HasFactory;

    protected $table = 'producto_empaques';

    protected $fillable = [
        'producto_id',
        'unidad_medida_id',
        'factor_conversion',
    ];

    protected $casts = [
        'factor_conversion' => 'integer',
    ];

    /**
     * Get the product that owns this packaging setup.
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Get the unit of measure of this packaging.
     *
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }
}
