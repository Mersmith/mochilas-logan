<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pobla `paises` y `ubigeos` con los datos de Perú
 * (28 Regiones, 194 Provincias, 1831 Distritos).
 *
 * Lee los datos desde los archivos de apoyo existentes:
 *   apoyo/RegionSeeder.php
 *   apoyo/ProvinciaSeeder.php
 *   apoyo/DistritoSeeder.php
 *
 * Cómo funciona el mapeo de IDs:
 *   Los seeders originales usan tablas separadas (regions, provincias, distritos)
 *   con sus propios IDs autoincrementales. En `ubigeos`, todo comparte una sola
 *   secuencia. Este seeder construye un mapa old_id → new_ubigeo_id para
 *   respetar la jerarquía padre-hijo sin perder la relación.
 */
class UbigeoPeruSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. País: Perú ─────────────────────────────────────────────────────
        $peruId = DB::table('paises')->insertGetId([
            'nombre' => 'Perú',
            'codigo_iso2' => 'PE',
            'codigo_iso3' => 'PER',
            'prefijo_telefono' => '+51',
            'label_nivel1' => 'Región',
            'label_nivel2' => 'Provincia',
            'label_nivel3' => 'Distrito',
            'simbolo_moneda' => 'S/.',
            'codigo_moneda' => 'PEN',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── 2. Regiones (nivel 1) ─────────────────────────────────────────────
        $regionMap = $this->insertRegiones($peruId);

        // ── 3. Provincias (nivel 2) ───────────────────────────────────────────
        $provinciaMap = $this->insertProvincias($peruId, $regionMap);

        // ── 4. Distritos (nivel 3) ────────────────────────────────────────────
        $this->insertDistritos($peruId, $provinciaMap);

        $this->command->info('✅ Perú cargado: '.count($regionMap).' regiones, '.count($provinciaMap).' provincias, 1831 distritos.');
    }

    // ─── Parsers ──────────────────────────────────────────────────────────────

    /**
     * Lee un archivo de apoyo y extrae los VALUES del INSERT con regex.
     * Patrón esperado: (id, 'NOMBRE', parent_id)
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function parse(string $archivo): array
    {
        $contenido = file_get_contents(base_path("apoyo/{$archivo}"));
        preg_match_all('/\((\d+),\s*\'([^\']+)\',\s*(\d+)\)/', $contenido, $matches, PREG_SET_ORDER);

        return $matches; // Cada elemento: [full, id, nombre, parent_old_id]
    }

    // ─── Inserts ──────────────────────────────────────────────────────────────

    /**
     * Inserta regiones y devuelve el mapa old_region_id → new_ubigeo_id.
     *
     * @return array<int, int>
     */
    private function insertRegiones(int $peruId): array
    {
        $regionMap = [];

        foreach ($this->parse('RegionSeeder.php') as $fila) {
            [, $oldId, $nombre] = $fila; // El 3er campo es pais_id (siempre 1), lo ignoramos

            $regionMap[(int) $oldId] = DB::table('ubigeos')->insertGetId([
                'pais_id' => $peruId,
                'parent_id' => null,
                'nivel' => 1,
                'nombre' => $nombre,
                'codigo' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $regionMap;
    }

    /**
     * Inserta provincias usando el mapa de regiones y devuelve old_provincia_id → new_ubigeo_id.
     *
     * @param  array<int, int>  $regionMap
     * @return array<int, int>
     */
    private function insertProvincias(int $peruId, array $regionMap): array
    {
        $provinciaMap = [];

        foreach ($this->parse('ProvinciaSeeder.php') as $fila) {
            [, $oldId, $nombre, $oldRegionId] = $fila;

            $provinciaMap[(int) $oldId] = DB::table('ubigeos')->insertGetId([
                'pais_id' => $peruId,
                'parent_id' => $regionMap[(int) $oldRegionId] ?? null,
                'nivel' => 2,
                'nombre' => $nombre,
                'codigo' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $provinciaMap;
    }

    /**
     * Inserta distritos en lotes de 500 registros para mayor rendimiento.
     *
     * @param  array<int, int>  $provinciaMap
     */
    private function insertDistritos(int $peruId, array $provinciaMap): void
    {
        $inserts = [];
        $ahora = now()->toDateTimeString();

        foreach ($this->parse('DistritoSeeder.php') as $fila) {
            [, , $nombre, $oldProvinciaId] = $fila;

            $inserts[] = [
                'pais_id' => $peruId,
                'parent_id' => $provinciaMap[(int) $oldProvinciaId] ?? null,
                'nivel' => 3,
                'nombre' => $nombre,
                'codigo' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($inserts, 500) as $lote) {
            DB::table('ubigeos')->insert($lote);
        }
    }
}
