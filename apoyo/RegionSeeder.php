<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::unprepared("
        INSERT INTO `regions` (`id`, `nombre`, `pais_id`) VALUES
        (1, 'AMAZONAS', 1),
        (2, 'ANCASH', 1),
        (3, 'APURIMAC', 1),
        (4, 'AREQUIPA', 1),
        (5, 'AYACUCHO', 1),
        (6, 'CAJAMARCA', 1),
        (7, 'CUSCO', 1),
        (8, 'HUANCAVELICA', 1),
        (9, 'HUANUCO', 1),
        (10, 'ICA', 1),
        (11, 'JUNIN', 1),
        (12, 'LA LIBERTAD', 1),
        (13, 'LAMBAYEQUE', 1),
        (14, 'LIMA', 1),
        (15, 'LORETO', 1),
        (16, 'MADRE DE DIOS', 1),
        (17, 'MOQUEGUA', 1),
        (18, 'PASCO', 1),
        (19, 'PIURA', 1),
        (20, 'PUNO', 1),
        (21, 'SAN MARTIN', 1),
        (22, 'TACNA', 1),
        (23, 'TUMBES', 1),
        (24, 'UCAYALI', 1),
        (25, 'LIMA PROVINCIAS', 1),
        (26, 'CALLAO', 1),
        (27, 'RESIDENTES EN EL EXTRANJERO', 1),
        (28, 'NACIONAL (PERU)', 1);
    ");
    }
}
