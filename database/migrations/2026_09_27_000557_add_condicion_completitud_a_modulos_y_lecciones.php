<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERS RF-67: el Profesor define cuándo una Lección o un Módulo se considera
 * completado. Los valores por defecto reproducen el comportamiento anterior
 * (lección marcada como vista; módulo con sus lecciones, tareas y quizzes). Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecciones', function (Blueprint $table) {
            $table->string('condicion_completitud', 30)->default('marcar');
        });

        Schema::table('modulos', function (Blueprint $table) {
            $table->string('condicion_completitud', 30)->default('actividades');
        });
    }

    public function down(): void
    {
        Schema::table('lecciones', function (Blueprint $table) {
            $table->dropColumn('condicion_completitud');
        });

        Schema::table('modulos', function (Blueprint $table) {
            $table->dropColumn('condicion_completitud');
        });
    }
};
