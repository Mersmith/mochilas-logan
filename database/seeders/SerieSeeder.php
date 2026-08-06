<?php

namespace Database\Seeders;

use App\Models\Sede;
use App\Models\Serie;
use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;

class SerieSeeder extends Seeder
{
    public function run(): void
    {
        // 5. Crear Series
        $sedeCentral = Sede::where('nombre', 'Sede Central Lima')->firstOrFail();

        $docBoleta = TipoDocumento::where('nombre', 'Boleta de Venta')->firstOrFail();
        $docFactura = TipoDocumento::where('nombre', 'Factura')->firstOrFail();
        $docGuiaEntrada = TipoDocumento::where('nombre', 'Guía de Entrada')->firstOrFail();
        $docGuiaSalida = TipoDocumento::where('nombre', 'Guía de Salida')->firstOrFail();
        $docGuiaTransferencia = TipoDocumento::where('nombre', 'Guía de Transferencia')->firstOrFail();

        Serie::firstOrCreate(
            ['sede_id' => $sedeCentral->id, 'tipo_documento_id' => $docBoleta->id, 'serie' => 'B001'],
            ['correlativo' => 100]
        );
        Serie::firstOrCreate(
            ['sede_id' => $sedeCentral->id, 'tipo_documento_id' => $docFactura->id, 'serie' => 'F001'],
            ['correlativo' => 100]
        );
        Serie::firstOrCreate(
            ['sede_id' => $sedeCentral->id, 'tipo_documento_id' => $docGuiaEntrada->id, 'serie' => 'GE01'],
            ['correlativo' => 1]
        );
        Serie::firstOrCreate(
            ['sede_id' => $sedeCentral->id, 'tipo_documento_id' => $docGuiaSalida->id, 'serie' => 'GS01'],
            ['correlativo' => 1]
        );
        Serie::firstOrCreate(
            ['sede_id' => $sedeCentral->id, 'tipo_documento_id' => $docGuiaTransferencia->id, 'serie' => 'GT01'],
            ['correlativo' => 1]
        );
    }
}
