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
        Schema::create('variacion_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variacion_id')->constrained('variacions')->onDelete('cascade');
            $table->foreignId('atributo_valor_id')->constrained('atributo_valores')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['variacion_id', 'atributo_valor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variacion_valores');
    }
};
