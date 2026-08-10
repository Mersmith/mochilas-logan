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
        Schema::create('direcciones', function (Blueprint $table) {
            $table->id();

            // Pertenece al perfil de cliente
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');

            // Etiqueta amigable que el cliente asigna (Ej: "Casa", "Trabajo", "Tienda Lima")
            $table->string('alias', 50)->default('Casa');

            // Nombre de quien recibe el paquete (puede ser diferente al cliente)
            $table->string('destinatario')->nullable();
            $table->string('telefono_contacto', 15)->nullable();

            // Dirección completa
            $table->string('direccion');
            $table->string('referencia')->nullable(); // Ej: "Frente al parque", "Piso 3"
            $table->string('distrito');
            $table->string('provincia');
            $table->string('departamento');
            $table->string('codigo_postal', 10)->nullable();

            // Solo una dirección puede ser la predeterminada por cliente
            $table->boolean('es_predeterminada')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direcciones');
    }
};
