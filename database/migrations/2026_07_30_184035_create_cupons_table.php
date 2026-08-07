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
        Schema::create('cupons', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->enum('tipo_descuento', ['fijo', 'porcentaje']);
            $table->decimal('valor_descuento', 10, 2);
            $table->decimal('monto_minimo_compra', 10, 2)->default(0);
            $table->integer('usos_totales')->default(1);
            $table->integer('usos_restantes')->default(1);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_expiracion')->nullable();
            $table->boolean('activo')->default(true);
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cupons');
    }
};
