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
        Schema::table('ventas', function (Blueprint $table) {
            // Referencia a la dirección usada (nullable si es venta física en tienda)
            $table->foreignId('direccion_id')
                ->nullable()
                ->after('cupon_id')
                ->constrained('direcciones')
                ->onDelete('set null');

            // Snapshot de la dirección al momento de la compra
            // Se copia para preservar el historial aunque el cliente modifique sus direcciones
            $table->string('envio_destinatario')->nullable()->after('direccion_id');
            $table->string('envio_telefono', 15)->nullable()->after('envio_destinatario');
            $table->string('envio_direccion')->nullable()->after('envio_telefono');
            $table->string('envio_referencia')->nullable()->after('envio_direccion');
            $table->string('envio_distrito')->nullable()->after('envio_referencia');
            $table->string('envio_provincia')->nullable()->after('envio_distrito');
            $table->string('envio_departamento')->nullable()->after('envio_provincia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['direccion_id']);
            $table->dropColumn([
                'direccion_id',
                'envio_destinatario',
                'envio_telefono',
                'envio_direccion',
                'envio_referencia',
                'envio_distrito',
                'envio_provincia',
                'envio_departamento',
            ]);
        });
    }
};
