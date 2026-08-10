<?php

namespace Database\Factories;

use App\Models\Pais;
use App\Models\Ubigeo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ubigeo>
 */
class UbigeoFactory extends Factory
{
    /**
     * Define the model's default state (nivel 1 por defecto).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pais_id' => Pais::factory(),
            'parent_id' => null,
            'nivel' => 1,
            'nombre' => fake()->state(),
            'codigo' => null,
        ];
    }

    /**
     * Ubigeo de nivel 1: Departamento / Región.
     */
    public function departamento(?int $paisId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'pais_id' => $paisId ?? $attributes['pais_id'],
            'parent_id' => null,
            'nivel' => 1,
        ]);
    }

    /**
     * Ubigeo de nivel 2: Provincia / Municipio — requiere un departamento padre.
     */
    public function provinciaOf(Ubigeo $departamento): static
    {
        return $this->state(fn (array $attributes) => [
            'pais_id' => $departamento->pais_id,
            'parent_id' => $departamento->id,
            'nivel' => 2,
        ]);
    }

    /**
     * Ubigeo de nivel 3: Distrito / Localidad — requiere una provincia padre.
     */
    public function distritoOf(Ubigeo $provincia): static
    {
        return $this->state(fn (array $attributes) => [
            'pais_id' => $provincia->pais_id,
            'parent_id' => $provincia->id,
            'nivel' => 3,
        ]);
    }
}
