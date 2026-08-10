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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            // Relación 1:1 con users (auth)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Tipo de persona: natural (DNI) o jurídica (RUC + razón social)
            $table->enum('tipo_persona', ['natural', 'juridica'])->default('natural');

            // Clasificación comercial del cliente
            $table->enum('tipo_cliente', ['minorista', 'mayorista', 'emprendedor'])->default('minorista');

            // Lista de precios asignada según tipo de cliente
            $table->foreignId('lista_precio_id')->constrained('lista_precios')->onDelete('restrict');

            // Documentos de identificación
            $table->string('dni', 8)->nullable()->unique();
            $table->string('ruc', 11)->nullable()->unique();
            $table->string('razon_social')->nullable(); // Obligatorio si tipo_persona = 'juridica'

            // Contacto principal
            $table->string('telefono', 15)->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Un user solo puede tener un perfil de cliente
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
