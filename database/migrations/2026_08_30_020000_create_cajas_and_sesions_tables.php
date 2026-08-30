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
        // 1. Cajas físicas por Sede
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->onDelete('cascade');
            $table->string('nombre'); // Ej: Caja 1 - Mostrador Principal
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Sesiones / Turnos de Caja
        Schema::create('caja_sesions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Cajero responsable
            $table->decimal('monto_inicial', 10, 2)->default(0); // Fondo de sencillo
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();

            // Cuadre / Arqueo de Caja
            $table->decimal('monto_cierre_efectivo', 10, 2)->nullable(); // Conteo físico real
            $table->decimal('monto_cierre_esperado', 10, 2)->nullable(); // Calculado por sistema
            $table->decimal('diferencia_efectivo', 10, 2)->nullable(); // Sobrante (+) o Faltante (-)

            // Resumen por Medios de Pago
            $table->decimal('total_ventas_efectivo', 10, 2)->default(0);
            $table->decimal('total_ventas_tarjeta', 10, 2)->default(0);
            $table->decimal('total_ventas_transferencia', 10, 2)->default(0);
            $table->decimal('total_ventas_yape_plin', 10, 2)->default(0);

            // Movimientos de Caja Chica
            $table->decimal('total_ingresos_extra', 10, 2)->default(0);
            $table->decimal('total_egresos_gastos', 10, 2)->default(0);

            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');
            $table->text('observaciones_apertura')->nullable();
            $table->text('observaciones_cierre')->nullable();
            $table->timestamps();
        });

        // 3. Movimientos de Caja Chica (Gastos e Ingresos Menores)
        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_sesion_id')->constrained('caja_sesions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Quien registra
            $table->enum('tipo', ['ingreso', 'egreso']);
            $table->decimal('monto', 10, 2);
            $table->string('motivo'); // Motivo o concepto
            $table->string('comprobante')->nullable(); // Nro. de ticket, recibo o boleta de gasto
            $table->timestamps();
        });

        // 4. Agregar caja_sesion_id a ventas
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('caja_sesion_id')->nullable()->after('tipo_documento_id')->constrained('caja_sesions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['caja_sesion_id']);
            $table->dropColumn('caja_sesion_id');
        });

        Schema::dropIfExists('caja_movimientos');
        Schema::dropIfExists('caja_sesions');
        Schema::dropIfExists('cajas');
    }
};
