<?php

namespace Database\Factories;

use App\Models\Marca;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Marca>
 */
class MarcaFactory extends Factory
{
    protected $model = Marca::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->randomElement(['Logan', 'Samsonite', 'Nike', 'Adidas', 'Totto']);

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'descripcion' => $this->faker->sentence(),
            'activo' => true,
        ];
    }
}
