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
            SedeSeeder::class,
            AlmacenSeeder::class,

                // 2. Catálogos base
            UnidadMedidaSeeder::class,
            AtributoSeeder::class,
            TipoDocumentoSeeder::class,
            SerieSeeder::class,

                // 3. Catálogos con dependencias
            TipoProductoSeeder::class,
            MarcaSeeder::class,
            CategoriaSeeder::class,

                // 4. Catálogos Comerciales
            ListaPrecioSeeder::class,
            ProveedorSeeder::class,

                // 5. Geografía (Paises → Ubigeos → Clientes)
            UbigeoPeruSeeder::class,   // Crea Perú en paises + 28 regiones, 194 provincias, 1831 distritos
            ClienteSeeder::class,       // Crea clientes demo con direcciones en Lima

                // 6. Entidades complejas
            ProductoSeeder::class,

                // 7. Transacciones iniciales
            GuiaInventarioSeeder::class,
        ]);
    }
}
