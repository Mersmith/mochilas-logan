<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\TipoProducto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->words(2, true);

        return [
            'tipo_producto_id' => TipoProducto::factory(),
            'marca_id' => Marca::factory(),
            'categoria_id' => Categoria::factory(),
            'nombre' => ucfirst($nombre),
            'slug' => Str::slug($nombre),
            'descripcion' => $this->faker->paragraph(),
            'activo' => true,
        ];
    }
}
