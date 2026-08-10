<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direcciones', function (Blueprint $table) {
            // ── Eliminar campos de texto libre geográfico ─────────────────────
            $table->dropColumn(['departamento', 'provincia', 'distrito']);

            // ── Agregar FK geográficas normalizadas ───────────────────────────
            // País de la dirección
            $table->foreignId('pais_id')
                ->nullable()
                ->after('cliente_id')
                ->constrained('paises')
                ->onDelete('restrict');

            // Nivel 1: Departamento / Región / Provincia (según país)
            $table->foreignId('departamento_id')
                ->nullable()
                ->after('pais_id')
                ->constrained('ubigeos')
                ->onDelete('restrict');

            // Nivel 2: Provincia / Municipio / Cantón (según país)
            $table->foreignId('provincia_id')
                ->nullable()
                ->after('departamento_id')
                ->constrained('ubigeos')
                ->onDelete('restrict');

            // Nivel 3: Distrito / Municipio / Localidad (según país) — nullable porque algunos países solo usan 2 niveles
            $table->foreignId('distrito_id')
                ->nullable()
                ->after('provincia_id')
                ->constrained('ubigeos')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('direcciones', function (Blueprint $table) {
            $table->dropForeign(['pais_id']);
            $table->dropForeign(['departamento_id']);
            $table->dropForeign(['provincia_id']);
            $table->dropForeign(['distrito_id']);

            $table->dropColumn(['pais_id', 'departamento_id', 'provincia_id', 'distrito_id']);

            $table->string('departamento')->nullable();
            $table->string('provincia')->nullable();
            $table->string('distrito')->nullable();
        });
    }
};
