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
        Schema::create('resultados_quiz', function (Blueprint $table) {
        $table->id();
        $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
        $table->foreignId('estudiante_id')->constrained('users')->onDelete('cascade');
        $table->jsonb('respuestas');
        $table->decimal('puntaje', 4, 2);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resultados_quiz');
    }
};
