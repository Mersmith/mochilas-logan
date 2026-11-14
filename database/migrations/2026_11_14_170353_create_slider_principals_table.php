<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('slider_principals', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('link')->nullable();
            $table->string('img_computadora'); // Ruta de la imagen desktop
            $table->string('img_movil');      // Ruta de la imagen mobile
            $table->integer('orden')->default(0); // Para ordenar cuál va primero
            $table->boolean('activo')->default(true); // Para activar/desactivar sin borrar
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slider_principals');
    }
};
