<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Puntaje opcional de cada pregunta del banco (la importación desde
 * Excel/CSV lo trae). Nullable: las preguntas existentes siguen valiendo 1
 * punto al agregarlas a un quiz (BancoPregunta::aPreguntaDeQuiz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banco_preguntas', function (Blueprint $table) {
            $table->decimal('puntaje', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('banco_preguntas', function (Blueprint $table) {
            $table->dropColumn('puntaje');
        });
    }
};
