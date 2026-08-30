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
        Schema::create('empresa_configuracions', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_comercial')->default('Mochilas Logan');
            $table->string('razon_social')->nullable();
            $table->string('ruc', 20)->nullable();
            $table->string('direccion_fiscal')->nullable();
            $table->string('telefono_contacto', 30)->nullable();
            $table->string('whatsapp_pedidos', 30)->default('51916647675');
            $table->string('email_contacto')->nullable();
            $table->string('horario_atencion')->nullable();

            // Redes Sociales
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('tiktok_url')->nullable();

            // Parámetros E-commerce / ERP
            $table->foreignId('almacen_web_id')->nullable()->constrained('almacens')->nullOnDelete();
            $table->foreignId('tipo_documento_web_id')->nullable()->constrained('tipo_documentos')->nullOnDelete();
            $table->decimal('costo_envio_fijo', 8, 2)->default(9.90);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_configuracions');
    }
};
