<?php

namespace Database\Factories;

use App\Models\ListaPrecio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListaPrecio>
 */
class ListaPrecioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->randomElement([
                'Precio Mayor', 'Precio Menor', 'Distribuidor', 'Emprendedor', 'VIP',
            ]),
            'activo' => true,
        ];
    }
}
