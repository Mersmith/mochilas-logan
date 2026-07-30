<?php

namespace Database\Factories;

use App\Models\Almacen;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Almacen>
 */
class AlmacenFactory extends Factory
{
    protected $model = Almacen::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sede_id' => Sede::factory(),
            'nombre' => 'Almacén '.$this->faker->word(),
            'ubicacion' => $this->faker->secondaryAddress(),
            'activo' => true,
        ];
    }
}
