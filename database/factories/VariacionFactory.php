<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Variacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Variacion>
 */
class VariacionFactory extends Factory
{
    protected $model = Variacion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sku = strtoupper($this->faker->unique()->bothify('SKU-####-????'));

        return [
            'producto_id' => Producto::factory(),
            'sku' => $sku,
            'codigo_barras' => $this->faker->unique()->ean13(),
            'activo' => true,
        ];
    }
}
