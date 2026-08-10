<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubigeos', function (Blueprint $table) {
            $table->id();

            // País al que pertenece este ubigeo
            $table->foreignId('pais_id')->constrained('paises')->onDelete('cascade');

            // Jerarquía auto-referencial: null si es nivel 1 (departamento/región/provincia)
            $table->foreignId('parent_id')->nullable()->constrained('ubigeos')->onDelete('cascade');

            // Nivel jerárquico: 1 = Departamento, 2 = Provincia, 3 = Distrito/Municipio
            $table->tinyInteger('nivel');

            $table->string('nombre');

            // Código oficial del país (ej: "150101" para Lima-Lima-Lima en Perú, código INEI)
            $table->string('codigo', 20)->nullable();

            $table->timestamps();

            $table->index(['pais_id', 'nivel']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubigeos');
    }
};
