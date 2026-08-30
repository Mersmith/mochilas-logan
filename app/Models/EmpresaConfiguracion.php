<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaConfiguracion extends Model
{
    use HasFactory;

    protected $table = 'empresa_configuracions';

    protected $fillable = [
        'nombre_comercial',
        'razon_social',
        'ruc',
        'direccion_fiscal',
        'telefono_contacto',
        'whatsapp_pedidos',
        'email_contacto',
        'horario_atencion',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'almacen_web_id',
        'tipo_documento_web_id',
        'costo_envio_fijo',
    ];

    protected $casts = [
        'costo_envio_fijo' => 'decimal:2',
    ];

    public function almacenWeb(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_web_id');
    }

    public function tipoDocumentoWeb(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_web_id');
    }

    /**
     * Obtener o instanciar la configuración global (Singleton pattern).
     */
    public static function getEmpresa(): self
    {
        return self::firstOrCreate([], [
            'nombre_comercial' => 'Mochilas Logan',
            'razon_social' => 'Mochilas Logan S.A.C.',
            'ruc' => '20600000000',
            'direccion_fiscal' => 'Lima, Perú',
            'telefono_contacto' => '+51 916 647 675',
            'whatsapp_pedidos' => '51916647675',
            'email_contacto' => 'ventas@mochilaslogan.com',
            'horario_atencion' => 'Lunes a Sábado 9:00 AM - 7:00 PM',
            'costo_envio_fijo' => 9.90,
        ]);
    }
}
