<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'razon_social' => $this->faker->company().' S.A.C.',
            'ruc' => $this->faker->unique()->numerify('20#########'),
            'direccion' => $this->faker->address(),
            'contacto_nombre' => $this->faker->name(),
            'contacto_celular' => $this->faker->numerify('9########'),
            'activo' => true,
        ];
    }
}
