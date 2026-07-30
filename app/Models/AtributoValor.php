<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AtributoValor extends Model
{
    use HasFactory;

    protected $table = 'atributo_valores';

    protected $fillable = [
        'atributo_id',
        'valor',
        'codigo_color_hex',
    ];

    /**
     * Get the attribute that owns the value.
     *
     * @return BelongsTo<Atributo, $this>
     */
    public function atributo(): BelongsTo
    {
        return $this->belongsTo(Atributo::class);
    }

    /**
     * Get the variations that use this value.
     *
     * @return BelongsToMany<Variacion, $this>
     */
    public function variacions(): BelongsToMany
    {
        return $this->belongsToMany(Variacion::class, 'variacion_valores');
    }
}
