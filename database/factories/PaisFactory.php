<?php

namespace Database\Factories;

use App\Models\Pais;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pais>
 */
class PaisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->country(),
            'codigo_iso2' => fake()->unique()->countryCode(),
            'codigo_iso3' => fake()->unique()->countryISOAlpha3(),
            'prefijo_telefono' => '+'.fake()->numerify('##'),
            'label_nivel1' => 'Departamento',
            'label_nivel2' => 'Provincia',
            'label_nivel3' => 'Distrito',
            'simbolo_moneda' => null,
            'codigo_moneda' => null,
            'activo' => true,
        ];
    }

    /**
     * País Perú preconfigurado.
     */
    public function peru(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Perú',
            'codigo_iso2' => 'PE',
            'codigo_iso3' => 'PER',
            'prefijo_telefono' => '+51',
            'label_nivel1' => 'Departamento',
            'label_nivel2' => 'Provincia',
            'label_nivel3' => 'Distrito',
            'simbolo_moneda' => 'S/.',
            'codigo_moneda' => 'PEN',
        ]);
    }

    /**
     * País Bolivia preconfigurado.
     */
    public function bolivia(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Bolivia',
            'codigo_iso2' => 'BO',
            'codigo_iso3' => 'BOL',
            'prefijo_telefono' => '+591',
            'label_nivel1' => 'Departamento',
            'label_nivel2' => 'Provincia',
            'label_nivel3' => 'Municipio',
            'simbolo_moneda' => 'Bs.',
            'codigo_moneda' => 'BOB',
        ]);
    }

    /**
     * País Argentina preconfigurado.
     */
    public function argentina(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Argentina',
            'codigo_iso2' => 'AR',
            'codigo_iso3' => 'ARG',
            'prefijo_telefono' => '+54',
            'label_nivel1' => 'Provincia',
            'label_nivel2' => 'Partido',
            'label_nivel3' => 'Localidad',
            'simbolo_moneda' => '$',
            'codigo_moneda' => 'ARS',
        ]);
    }
}
