<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->word();

        return [
            'categoria_padre_id' => null,
            'codigo' => strtoupper($this->faker->unique()->lexify('CAT???')),
            'nombre' => ucfirst($nombre),
            'slug' => Str::slug($nombre),
            'descripcion' => $this->faker->sentence(),
            'orden' => 0,
            'activo' => true,
        ];
    }
}
