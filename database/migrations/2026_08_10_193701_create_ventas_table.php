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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('tipo_documento_id')->constrained('tipo_documentos')->onDelete('restrict');
            $table->string('serie', 10);
            $table->integer('correlativo');

            $table->enum('estado_pago', ['pendiente', 'pagado', 'reembolsado', 'cancelado'])->default('pendiente');
            $table->enum('estado_despacho', ['pendiente', 'preparado', 'despachado', 'entregado'])->default('pendiente');

            $table->decimal('subtotal', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('costo_envio', 10, 2)->default(0);
            $table->decimal('total', 10, 2);

            $table->enum('tipo_pago', ['online', 'contraentrega']);
            $table->string('metodo_pago')->nullable();

            $table->foreignId('direccion_id')->nullable()->constrained('direcciones')->onDelete('set null');
            $table->string('envio_destinatario')->nullable();
            $table->string('envio_telefono', 15)->nullable();
            $table->string('envio_direccion')->nullable();
            $table->string('envio_referencia')->nullable();
            $table->string('envio_distrito')->nullable();
            $table->string('envio_provincia')->nullable();
            $table->string('envio_departamento')->nullable();

            $table->foreignId('cupon_id')->nullable()->constrained('cupons')->onDelete('set null');
            $table->text('comentarios')->nullable();
            $table->timestamps();

            $table->unique(['tipo_documento_id', 'serie', 'correlativo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
