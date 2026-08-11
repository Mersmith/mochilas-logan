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
        Schema::create('medio_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->enum('tipo', ['tarjeta', 'yape', 'pagoefectivo'])->default('tarjeta');
            $table->string('proveedor')->nullable(); // 'visa', 'mastercard', etc
            $table->string('ultimos_cuatro', 4)->nullable();
            $table->string('fecha_expiracion', 5)->nullable(); // MM/AA
            $table->boolean('es_predeterminado')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medio_pagos');
    }
};
