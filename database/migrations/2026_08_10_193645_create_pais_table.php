<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paises', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');           // "Perú", "Bolivia", "Argentina"
            $table->string('codigo_iso2', 2)->unique(); // "PE", "BO", "AR"
            $table->string('codigo_iso3', 3)->unique(); // "PER", "BOL", "ARG"
            $table->string('prefijo_telefono', 6)->nullable(); // "+51", "+591"

            // Cómo se llama cada nivel geográfico en este país
            $table->string('label_nivel1')->default('Departamento'); // "Región", "Provincia", "Estado"
            $table->string('label_nivel2')->default('Provincia');    // "Municipio", "Cantón", "Partido"
            $table->string('label_nivel3')->default('Distrito');     // "Municipio", "Localidad", "Parroquia"

            // Moneda local
            $table->string('simbolo_moneda', 5)->nullable();  // "S/.", "Bs.", "$"
            $table->string('codigo_moneda', 3)->nullable();   // "PEN", "BOB", "ARS"

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paises');
    }
};
