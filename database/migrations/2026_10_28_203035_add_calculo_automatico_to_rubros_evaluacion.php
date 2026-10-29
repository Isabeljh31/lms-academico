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
        Schema::table('rubros_evaluacion', function (Blueprint $table) {
            $table->boolean('calculo_automatico')->default(false);
        });

        Schema::create('rubro_tarea', function (Blueprint $table) {
            $table->foreignId('rubro_id')->constrained('rubros_evaluacion')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->primary(['rubro_id', 'tarea_id']);
        });

        Schema::create('rubro_quiz', function (Blueprint $table) {
            $table->foreignId('rubro_id')->constrained('rubros_evaluacion')->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->primary(['rubro_id', 'quiz_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rubro_quiz');
        Schema::dropIfExists('rubro_tarea');
        Schema::table('rubros_evaluacion', function (Blueprint $table) {
            $table->dropColumn('calculo_automatico');
        });
    }
};
