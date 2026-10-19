<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERS RF-66 y RF-67: registro de lecciones completadas por cada estudiante,
     * usado en el cálculo del porcentaje de avance.
     */
    public function up(): void
    {
        Schema::create('lecciones_completadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leccion_id')->constrained('lecciones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completada_en')->useCurrent();

            $table->unique(['leccion_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecciones_completadas');
    }
};
