<?php

namespace Database\Seeders;

use App\Models\AtributoValor;
use App\Models\Categoria;
use App\Models\ListaPrecio;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoEmpaque;
use App\Models\TipoProducto;
use App\Models\UnidadMedida;
use App\Models\Variacion;
use App\Models\VariacionPrecio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        // 13. Crear un Producto Base
        $tipoMochila = TipoProducto::where('nombre', 'Mochila')->firstOrFail();
        $marcaLogan = Marca::where('nombre', 'Logan')->firstOrFail();
        $subUrbanas = Categoria::where('nombre', 'Urbanas')->firstOrFail();

        $prodOxford = Producto::firstOrCreate(
            ['slug' => 'mochila-logan-oxford'],
            [
                'tipo_producto_id' => $tipoMochila->id,
                'marca_id' => $marcaLogan->id,
                'categoria_id' => $subUrbanas->id,
                'nombre' => 'Mochila Logan Oxford',
                'descripcion' => 'Mochila urbana de lona Oxford impermeable con compartimento para laptop de 15.6 pulgadas.',
                'activo' => true,
            ]
        );

        // Registrar empaques para este producto
        $uniUnidad = UnidadMedida::where('nombre', 'Unidad')->firstOrFail();
        $uniCaja = UnidadMedida::where('nombre', 'Caja')->firstOrFail();
        $uniCostal = UnidadMedida::where('nombre', 'Costal')->firstOrFail();

        ProductoEmpaque::firstOrCreate(['producto_id' => $prodOxford->id, 'unidad_medida_id' => $uniUnidad->id], ['factor_conversion' => 1]);
        ProductoEmpaque::firstOrCreate(['producto_id' => $prodOxford->id, 'unidad_medida_id' => $uniCaja->id], ['factor_conversion' => 100]);
        ProductoEmpaque::firstOrCreate(['producto_id' => $prodOxford->id, 'unidad_medida_id' => $uniCostal->id], ['factor_conversion' => 50]);

        // Atributos
        $colorNegro = AtributoValor::where('valor', 'Negro')->firstOrFail();
        $colorAzul = AtributoValor::where('valor', 'Azul')->firstOrFail();
        $tallaM = AtributoValor::where('valor', 'M')->firstOrFail();
        $tallaL = AtributoValor::where('valor', 'L')->firstOrFail();

        // 14. Crear Variaciones para el Producto
        $varNegroM = Variacion::firstOrCreate(
            ['sku' => 'LOG-OXF-NEG-M'],
            ['producto_id' => $prodOxford->id, 'codigo_barras' => '7750123456789', 'activo' => true]
        );
        $varNegroM->valores()->syncWithoutDetaching([$colorNegro->id, $tallaM->id]);

        $varNegroL = Variacion::firstOrCreate(
            ['sku' => 'LOG-OXF-NEG-L'],
            ['producto_id' => $prodOxford->id, 'codigo_barras' => '7750123456790', 'activo' => true]
        );
        $varNegroL->valores()->syncWithoutDetaching([$colorNegro->id, $tallaL->id]);

        $varAzulM = Variacion::firstOrCreate(
            ['sku' => 'LOG-OXF-AZU-M'],
            ['producto_id' => $prodOxford->id, 'codigo_barras' => '7750123456791', 'activo' => true]
        );
        $varAzulM->valores()->syncWithoutDetaching([$colorAzul->id, $tallaM->id]);

        // 15. Registrar precios por variación
        $listaMenor = ListaPrecio::where('nombre', 'Precio Menor')->firstOrFail();
        $listaMayor = ListaPrecio::where('nombre', 'Precio Mayor')->firstOrFail();

        VariacionPrecio::firstOrCreate(['variacion_id' => $varNegroM->id, 'lista_precio_id' => $listaMenor->id], ['precio' => 120.00, 'simbolo' => 'S/']);
        VariacionPrecio::firstOrCreate(['variacion_id' => $varNegroM->id, 'lista_precio_id' => $listaMayor->id], ['precio' => 95.00, 'simbolo' => 'S/']);

        VariacionPrecio::firstOrCreate(['variacion_id' => $varNegroL->id, 'lista_precio_id' => $listaMenor->id], ['precio' => 140.00, 'simbolo' => 'S/']);
        VariacionPrecio::firstOrCreate(['variacion_id' => $varNegroL->id, 'lista_precio_id' => $listaMayor->id], ['precio' => 110.00, 'simbolo' => 'S/']);

        VariacionPrecio::firstOrCreate(['variacion_id' => $varAzulM->id, 'lista_precio_id' => $listaMenor->id], ['precio' => 120.00, 'simbolo' => 'S/']);
        VariacionPrecio::firstOrCreate(['variacion_id' => $varAzulM->id, 'lista_precio_id' => $listaMayor->id], ['precio' => 95.00, 'simbolo' => 'S/']);

        // --- Generar 49 productos adicionales con imagen default ---
        $tipos = TipoProducto::all();
        $marcas = Marca::all();
        $categorias = Categoria::all();

        $productosAdicionales = Producto::factory(49)
            ->recycle($tipos)
            ->recycle($marcas)
            ->recycle($categorias)
            ->create();

        $imagePath = public_path('assets/imagen/default.jpg');

        // Asignar imagen al producto Oxford principal
        if (file_exists($imagePath)) {
            $prodOxford->addMedia($imagePath)->preservingOriginal()->toMediaCollection('imagen_principal');
        }

        // Asignar variaciones, precios e imágenes a los 49 productos adicionales
        foreach ($productosAdicionales as $producto) {
            if (file_exists($imagePath)) {
                $producto->addMedia($imagePath)->preservingOriginal()->toMediaCollection('imagen_principal');
                $producto->addMedia($imagePath)->preservingOriginal()->toMediaCollection('galeria');
            }

            // Crear 1 variación por producto
            $variacion = Variacion::firstOrCreate(
                ['sku' => 'SKU-' . strtoupper(Str::random(6))],
                [
                    'producto_id' => $producto->id,
                    'codigo_barras' => fake()->unique()->ean13(),
                    'activo' => true
                ]
            );

            // Crear precios para la variación
            VariacionPrecio::firstOrCreate([
                'variacion_id' => $variacion->id,
                'lista_precio_id' => $listaMenor->id
            ], [
                'precio' => fake()->randomFloat(2, 50, 300),
                'simbolo' => 'S/'
            ]);

            VariacionPrecio::firstOrCreate([
                'variacion_id' => $variacion->id,
                'lista_precio_id' => $listaMayor->id
            ], [
                'precio' => fake()->randomFloat(2, 40, 250),
                'simbolo' => 'S/'
            ]);
        }
    }
}
