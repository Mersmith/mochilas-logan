<?php

namespace Database\Seeders;

use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;

class TipoDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        // 4. Crear Tipos de Documentos
        TipoDocumento::firstOrCreate(['nombre' => 'Boleta de Venta'], ['codigo_sunat' => '03']);
        TipoDocumento::firstOrCreate(['nombre' => 'Factura'], ['codigo_sunat' => '01']);
        TipoDocumento::firstOrCreate(['nombre' => 'Guía de Entrada'], ['codigo_sunat' => '99']);
        TipoDocumento::firstOrCreate(['nombre' => 'Guía de Salida'], ['codigo_sunat' => '98']);
        TipoDocumento::firstOrCreate(['nombre' => 'Guía de Transferencia'], ['codigo_sunat' => '09']);
    }
}
