<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\ListaPrecio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipoCliente = fake()->randomElement(['minorista', 'mayorista', 'emprendedor']);

        return [
            'user_id' => User::factory(),
            'tipo_persona' => 'natural',
            'tipo_cliente' => $tipoCliente,
            'lista_precio_id' => ListaPrecio::factory(),
            'dni' => fake()->numerify('########'),
            'ruc' => null,
            'razon_social' => null,
            'telefono' => fake()->numerify('9########'),
            'activo' => true,
        ];
    }

    /**
     * Cliente de tipo minorista.
     */
    public function minorista(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_persona' => 'natural',
            'tipo_cliente' => 'minorista',
            'ruc' => null,
            'razon_social' => null,
        ]);
    }

    /**
     * Cliente de tipo mayorista (con RUC y razón social).
     */
    public function mayorista(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_persona' => 'juridica',
            'tipo_cliente' => 'mayorista',
            'ruc' => fake()->numerify('###########'),
            'razon_social' => fake()->company(),
            'dni' => null,
        ]);
    }

    /**
     * Cliente de tipo emprendedor.
     */
    public function emprendedor(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_persona' => 'juridica',
            'tipo_cliente' => 'emprendedor',
            'ruc' => fake()->numerify('###########'),
            'razon_social' => fake()->company(),
            'dni' => null,
        ]);
    }

    /**
     * Cliente inactivo.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
