<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        // 8. Crear Categorías
        $catMochilas = Categoria::firstOrCreate(
            ['nombre' => 'Mochilas'],
            ['slug' => 'mochilas', 'descripcion' => 'Todo tipo de mochilas']
        );

        Categoria::firstOrCreate(
            ['nombre' => 'Escolares', 'categoria_padre_id' => $catMochilas->id],
            ['slug' => 'escolares', 'descripcion' => 'Mochilas para el colegio']
        );

        Categoria::firstOrCreate(
            ['nombre' => 'Urbanas', 'categoria_padre_id' => $catMochilas->id],
            ['slug' => 'urbanas', 'descripcion' => 'Mochilas de uso diario']
        );
    }
}
