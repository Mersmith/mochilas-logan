<?php

namespace Database\Seeders;

use App\Models\Marca;
use Illuminate\Database\Seeder;

class MarcaSeeder extends Seeder
{
    public function run(): void
    {
        // 7. Crear Marcas
        Marca::firstOrCreate(
            ['nombre' => 'Logan'],
            ['slug' => 'logan', 'descripcion' => 'Mochilas Logan Original']
        );
        Marca::firstOrCreate(
            ['nombre' => 'Samsonite'],
            ['slug' => 'samsonite', 'descripcion' => 'Maletas y carteras Samsonite']
        );
    }
}
