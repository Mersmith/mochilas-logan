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
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained()->onDelete('cascade');
            $table->foreignId('variacion_id')->constrained('variacions')->onDelete('cascade');
            $table->integer('stock_base')->default(0);
            $table->integer('stock_minimo')->default(0);
            $table->timestamps();

            $table->unique(['almacen_id', 'variacion_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
