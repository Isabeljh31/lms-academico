<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERS RF-47: banco de preguntas por curso y tema, reutilizable en quizzes.
     */
    public function up(): void
    {
        Schema::create('banco_preguntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->string('tema', 150);
            $table->text('pregunta');
            // opcion_unica | opcion_multiple | verdadero_falso
            $table->string('tipo', 30)->default('opcion_unica');
            $table->json('opciones');
            $table->json('respuestas_correctas');
            $table->text('explicacion')->nullable();
            $table->string('dificultad', 20)->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['curso_id', 'tema']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banco_preguntas');
    }
};
