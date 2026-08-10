<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Direccion;
use App\Models\Pais;
use App\Models\Ubigeo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Direccion>
 */
class DireccionFactory extends Factory
{
    /**
     * Define the model's default state.
     * Crea la cadena completa: Pais → Departamento → Provincia → Distrito.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pais = Pais::factory()->create();
        $departamento = Ubigeo::factory()->departamento($pais->id)->create(['pais_id' => $pais->id]);
        $provincia = Ubigeo::factory()->provinciaOf($departamento)->create();
        $distrito = Ubigeo::factory()->distritoOf($provincia)->create();

        return [
            'cliente_id' => Cliente::factory(),
            'pais_id' => $pais->id,
            'departamento_id' => $departamento->id,
            'provincia_id' => $provincia->id,
            'distrito_id' => $distrito->id,
            'alias' => fake()->randomElement(['Casa', 'Trabajo', 'Tienda', 'Depósito']),
            'destinatario' => fake()->name(),
            'telefono_contacto' => fake()->numerify('9########'),
            'direccion' => fake()->streetAddress(),
            'referencia' => fake()->optional()->sentence(4),
            'codigo_postal' => fake()->optional()->numerify('#####'),
            'es_predeterminada' => false,
            'activo' => true,
        ];
    }

    /**
     * Dirección marcada como predeterminada.
     */
    public function predeterminada(): static
    {
        return $this->state(fn (array $attributes) => [
            'es_predeterminada' => true,
        ]);
    }
}
