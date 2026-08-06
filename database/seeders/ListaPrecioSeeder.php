<?php

namespace Database\Seeders;

use App\Models\ListaPrecio;
use Illuminate\Database\Seeder;

class ListaPrecioSeeder extends Seeder
{
    public function run(): void
    {
        // 11. Crear Listas de Precios
        ListaPrecio::firstOrCreate(['nombre' => 'Precio Menor']);
        ListaPrecio::firstOrCreate(['nombre' => 'Precio Mayor']);
    }
}
