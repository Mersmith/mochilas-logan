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

            // 1. Geografía
            UbigeoPeruSeeder::class,   // Crea Perú en paises + 28 regiones, 194 provincias, 1831 distritos

            // 2. Organización
            SedeSeeder::class,
            AlmacenSeeder::class,

            // 3. Catálogos base
            UnidadMedidaSeeder::class,
            AtributoSeeder::class,
            TipoDocumentoSeeder::class,
            SerieSeeder::class,

            // 4. Catálogos con dependencias
            TipoProductoSeeder::class,
            MarcaSeeder::class,
            CategoriaSeeder::class,

            // 5. Catálogos Comerciales
            ListaPrecioSeeder::class,
            ProveedorSeeder::class,

            // 6. Clientes (depende de: Ubigeo, ListaPrecio, Roles)
            ClienteSeeder::class,

            // 7. Entidades complejas
            /*ProductoSeeder::class,

            // 8. Transacciones iniciales
            GuiaInventarioSeeder::class,*/
        ]);
    }
}
