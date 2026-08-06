<?php

namespace Database\Seeders;

use App\Models\Atributo;
use App\Models\AtributoValor;
use Illuminate\Database\Seeder;

class AtributoSeeder extends Seeder
{
    public function run(): void
    {
        // 10. Crear Atributos y Valores
        $attrColor = Atributo::firstOrCreate(['nombre' => 'Color']);
        AtributoValor::firstOrCreate(['atributo_id' => $attrColor->id, 'valor' => 'Negro'], ['codigo_color_hex' => '#000000']);
        AtributoValor::firstOrCreate(['atributo_id' => $attrColor->id, 'valor' => 'Azul'], ['codigo_color_hex' => '#0000FF']);
        AtributoValor::firstOrCreate(['atributo_id' => $attrColor->id, 'valor' => 'Rojo'], ['codigo_color_hex' => '#FF0000']);

        $attrTalla = Atributo::firstOrCreate(['nombre' => 'Talla']);
        AtributoValor::firstOrCreate(['atributo_id' => $attrTalla->id, 'valor' => 'M']);
        AtributoValor::firstOrCreate(['atributo_id' => $attrTalla->id, 'valor' => 'L']);
    }
}
