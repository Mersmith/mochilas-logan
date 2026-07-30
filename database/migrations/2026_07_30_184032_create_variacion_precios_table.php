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
        Schema::create('variacion_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variacion_id')->constrained('variacions')->onDelete('cascade');
            $table->foreignId('lista_precio_id')->constrained('lista_precios')->onDelete('cascade');
            $table->decimal('precio', 10, 2);
            $table->decimal('precio_antiguo', 10, 2)->nullable();
            $table->string('simbolo')->default('S/');
            $table->timestamps();

            $table->unique(['variacion_id', 'lista_precio_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variacion_precios');
    }
};
