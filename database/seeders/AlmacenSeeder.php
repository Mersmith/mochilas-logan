<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Sede;
use Illuminate\Database\Seeder;

class AlmacenSeeder extends Seeder
{
    public function run(): void
    {
        // 3. Crear Almacenes
        $sedeCentral = Sede::where('nombre', 'Sede Central Lima')->firstOrFail();

        Almacen::firstOrCreate(
            ['nombre' => 'Almacén Central Principal'],
            [
                'sede_id' => $sedeCentral->id,
                'ubicacion' => 'Sótano 1, Lima',
                'activo' => true,
            ]
        );

        $sedeNorte = Sede::where('nombre', 'Sede Sucursal Norte')->firstOrFail();

        Almacen::firstOrCreate(
            ['nombre' => 'Almacén Exhibición Norte'],
            [
                'sede_id' => $sedeNorte->id,
                'ubicacion' => 'Piso 1, Los Olivos',
                'activo' => true,
            ]
        );
    }
}
