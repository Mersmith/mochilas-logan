<?php

namespace Database\Factories;

use App\Models\SliderPrincipal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SliderPrincipal>
 */
class SliderPrincipalFactory extends Factory
{
    protected $model = SliderPrincipal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Banner '.fake()->words(2, true),
            'link' => fake()->boolean(70) ? '/catalogo' : null,
            'img_computadora' => 'assets/imagen/default.jpg',
            'img_movil' => 'assets/imagen/default.jpg',
            'orden' => fake()->numberBetween(0, 10),
            'activo' => true,
        ];
    }
}
