<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Atributo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
    ];

    /**
     * Get the values for this attribute.
     *
     * @return HasMany<AtributoValor, $this>
     */
    public function valores(): HasMany
    {
        return $this->hasMany(AtributoValor::class);
    }
}
