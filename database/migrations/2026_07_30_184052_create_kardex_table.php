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
        Schema::create('kardex', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('almacens')->onDelete('restrict');
            $table->foreignId('variacion_id')->constrained('variacions')->onDelete('restrict');
            $table->enum('tipo_transaccion', ['Entrada', 'Salida']);
            $table->string('concepto');

            $table->integer('cantidad');
            $table->integer('stock_anterior');
            $table->integer('stock_posterior');

            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->decimal('costo_total', 10, 2)->nullable();
            $table->decimal('valor_total_almacen', 12, 2)->nullable();

            $table->nullableMorphs('origen_documento');

            $table->foreignId('creado_por_usuario_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
