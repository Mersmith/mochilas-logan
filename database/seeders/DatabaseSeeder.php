<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 0. Seguridad (No depende de nadie)
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,

            // 1. Organización
            /*SedeSeeder::class,
            AlmacenSeeder::class,

            // 2. Catálogos base
            UnidadMedidaSeeder::class,
            AtributoSeeder::class,
            TipoDocumentoSeeder::class,
            SerieSeeder::class,

            // 3. Catálogos con dependencias (Categoría puede tener padre)
            TipoProductoSeeder::class,
            MarcaSeeder::class,
            CategoriaSeeder::class,

            // 4. Catálogos Comerciales
            ListaPrecioSeeder::class,
            ProveedorSeeder::class,

            // 5. Entidades complejas
            ProductoSeeder::class,

            // 6. Transacciones iniciales
            GuiaInventarioSeeder::class,*/
        ]);
    }
}
