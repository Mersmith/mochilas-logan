<?php

namespace Database\Factories;

use App\Models\TipoProducto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TipoProducto>
 */
class TipoProductoFactory extends Factory
{
    protected $model = TipoProducto::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->randomElement(['Mochila', 'Cartera', 'Maletín', 'Cartuchera', 'Cangurera']);

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'activo' => true,
        ];
    }
}
