<?php

namespace Database\Seeders;

use App\Models\SliderPrincipal;
use Illuminate\Database\Seeder;

class SliderPrincipalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sliders = [
            [
                'nombre' => 'Colección Escolar 2026',
                'link' => '/catalogo',
                'img_computadora' => 'assets/sliders/sliders-computadora-1.jpg',
                'img_movil' => 'assets/sliders/sliders-movil-1.jpg',
                'orden' => 1,
                'activo' => true,
            ],
            [
                'nombre' => 'Línea Urbana & Ejecutiva',
                'link' => '/catalogo',
                'img_computadora' => 'assets/sliders/sliders-computadora-2.jpg',
                'img_movil' => 'assets/sliders/sliders-movil-2.jpg',
                'orden' => 2,
                'activo' => true,
            ],
            [
                'nombre' => 'Mochilas Antirrobo y Viaje',
                'link' => '/catalogo',
                'img_computadora' => 'assets/sliders/sliders-computadora-3.jpg',
                'img_movil' => 'assets/sliders/sliders-movil-3.jpg',
                'orden' => 3,
                'activo' => true,
            ],
        ];

        foreach ($sliders as $slider) {
            SliderPrincipal::updateOrCreate(
                ['nombre' => $slider['nombre']],
                $slider
            );
        }
    }
}
