<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatoReembolso extends Model
{
    protected $fillable = [
        'cliente_id',
        'banco',
        'tipo_cuenta',
        'cci',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
