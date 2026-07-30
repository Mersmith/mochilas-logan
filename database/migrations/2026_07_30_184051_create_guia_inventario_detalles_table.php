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
        Schema::create('guia_inventario_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guia_inventario_id')->constrained('guias_inventario')->onDelete('cascade');
            $table->foreignId('variacion_id')->constrained('variacions')->onDelete('restrict');
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida')->onDelete('restrict');

            $table->integer('cantidad');
            $table->integer('factor_conversion')->default(1);
            $table->integer('cantidad_base');

            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->decimal('costo_total', 10, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guia_inventario_detalles');
    }
};
