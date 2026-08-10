<?php

namespace Database\Seeders;

use App\Models\Sede;
use Illuminate\Database\Seeder;

class SedeSeeder extends Seeder
{
    public function run(): void
    {
        // 2. Crear Sedes
        Sede::firstOrCreate(
            ['nombre' => 'Sede Central Lima'],
            [
                'direccion' => 'Av. Javier Prado 1234, San Isidro',
                'activo' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );

        Sede::firstOrCreate(
            ['nombre' => 'Sede Sucursal Norte'],
            [
                'direccion' => 'Av. Alfredo Mendiola 4567, Los Olivos',
                'activo' => true,
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );
        Sede::factory(30)->create();
    }
}
