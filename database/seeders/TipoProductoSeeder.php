<?php

namespace Database\Seeders;

use App\Models\TipoProducto;
use Illuminate\Database\Seeder;

class TipoProductoSeeder extends Seeder
{
    public function run(): void
    {
        // 6. Crear Tipos de Productos
        TipoProducto::firstOrCreate(['nombre' => 'Mochila'], ['slug' => 'mochila']);
        TipoProducto::firstOrCreate(['nombre' => 'Cartera'], ['slug' => 'cartera']);
        TipoProducto::firstOrCreate(['nombre' => 'Maletín'], ['slug' => 'maletin']);
    }
}
