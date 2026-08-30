<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Sede;
use Illuminate\Database\Seeder;

class CajaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sedes = Sede::all();
        foreach ($sedes as $sede) {
            Caja::firstOrCreate([
                'sede_id' => $sede->id,
                'nombre' => 'Caja 1 - '.$sede->nombre,
            ], [
                'activo' => true,
            ]);
        }
    }
}
