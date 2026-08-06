<?php

namespace Database\Seeders;

use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class ProveedorSeeder extends Seeder
{
    public function run(): void
    {
        // 12. Crear Proveedores
        Proveedor::firstOrCreate(
            ['ruc' => '20601234567'],
            [
                'razon_social' => 'Corporación Logan Import S.A.C.',
                'direccion' => 'Calle Los Álamos 450, San Isidro',
                'contacto_nombre' => 'Carlos Logan',
                'contacto_celular' => '999888777',
                'activo' => true,
            ]
        );
    }
}
