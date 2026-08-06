<?php

namespace Database\Seeders;

use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class UnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        // 9. Crear Unidades de Medida
        UnidadMedida::firstOrCreate(['nombre' => 'Unidad'], ['abreviacion' => 'UND']);
        UnidadMedida::firstOrCreate(['nombre' => 'Caja'], ['abreviacion' => 'CJ']);
        UnidadMedida::firstOrCreate(['nombre' => 'Costal'], ['abreviacion' => 'CST']);
    }
}
