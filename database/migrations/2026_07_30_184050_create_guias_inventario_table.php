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
        Schema::create('guias_inventario', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_movimiento', ['Entrada', 'Salida', 'Transferencia']);

            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->onDelete('restrict');

            $table->foreignId('sede_origen_id')->nullable()->constrained('sedes')->onDelete('restrict');
            $table->foreignId('almacen_origen_id')->nullable()->constrained('almacens')->onDelete('restrict');

            $table->foreignId('sede_destino_id')->nullable()->constrained('sedes')->onDelete('restrict');
            $table->foreignId('almacen_destino_id')->nullable()->constrained('almacens')->onDelete('restrict');

            $table->foreignId('tipo_documento_id')->constrained('tipo_documentos')->onDelete('restrict');
            $table->string('serie', 10);
            $table->integer('correlativo');

            $table->date('fecha_movimiento');
            $table->enum('estado', ['Borrador', 'En Tránsito', 'Procesado', 'Anulado'])->default('Borrador');
            $table->text('motivo')->nullable();

            $table->unsignedBigInteger('venta_id')->nullable(); // Relación débil con ventas

            $table->foreignId('creado_por_usuario_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->unique(['tipo_documento_id', 'serie', 'correlativo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guias_inventario');
    }
};
