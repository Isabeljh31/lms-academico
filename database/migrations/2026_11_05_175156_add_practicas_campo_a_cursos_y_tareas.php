<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prácticas de campo definidas por el Supervisor.
 *
 * - cursos.tiene_practicas_campo: lo decide el Supervisor en el formulario del
 *   curso. Hay una práctica por semana del curso (Práctica 1..N, N = semanas).
 * - tareas.practica: número de práctica (1..N) de la tarea que la representa;
 *   cada práctica tiene una sola tarea (entrega de archivos calificable).
 * - esquemas_evaluacion.peso_practicas_campo: % que el Supervisor reserva para
 *   las prácticas dentro de las evaluaciones (se reparte entre una o varias).
 * - rubros_evaluacion.practica_desde/practica_hasta/peso_practicas: tramo de
 *   prácticas que el profesor asigna a la evaluación y su % dentro de ella
 *   (partes iguales por práctica; ver PracticasEnEvaluaciones).
 *
 * Migración de datos: hasta ahora el Profesor creaba la sección "Prácticas de
 * campo" con las secciones fijas de una semana. Esas secciones se quitan; las
 * que ya tienen entregas o resultados de quiz (regla de negocio 3, historial
 * académico) se conservan: el curso queda con prácticas de campo y sus
 * tareas pasan a ser Práctica 1..k por fecha de entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->boolean('tiene_practicas_campo')->default(false);
        });

        Schema::table('tareas', function (Blueprint $table) {
            $table->unsignedSmallInteger('practica')->nullable();
            $table->unique(['curso_id', 'practica']);
        });

        Schema::table('esquemas_evaluacion', function (Blueprint $table) {
            $table->decimal('peso_practicas_campo', 5, 2)->nullable();
        });

        Schema::table('rubros_evaluacion', function (Blueprint $table) {
            $table->unsignedSmallInteger('practica_desde')->nullable();
            $table->unsignedSmallInteger('practica_hasta')->nullable();
            $table->decimal('peso_practicas', 5, 2)->nullable();
        });

        DB::table('modulos')->where('tipo_seccion', 'practicas_campo')->orderBy('id')->each(function (object $modulo) {
            $tareas = DB::table('tareas')->where('modulo_id', $modulo->id)->orderBy('fecha_entrega')->orderBy('id')->pluck('id');
            $quizzes = DB::table('quizzes')->where('modulo_id', $modulo->id)->pluck('id');

            $tieneHistorial = DB::table('entregas')->whereIn('tarea_id', $tareas)->exists()
                || DB::table('resultados_quiz')->whereIn('quiz_id', $quizzes)->exists();

            if (! $tieneHistorial) {
                DB::table('modulos')->where('id', $modulo->id)->delete();

                return;
            }

            foreach ($tareas->values() as $indice => $tareaId) {
                DB::table('tareas')->where('id', $tareaId)->update(['practica' => $indice + 1]);
            }

            DB::table('cursos')->where('id', $modulo->curso_id)->update(['tiene_practicas_campo' => true]);
        });
    }

    public function down(): void
    {
        Schema::table('rubros_evaluacion', function (Blueprint $table) {
            $table->dropColumn(['practica_desde', 'practica_hasta', 'peso_practicas']);
        });

        Schema::table('esquemas_evaluacion', function (Blueprint $table) {
            $table->dropColumn('peso_practicas_campo');
        });

        Schema::table('tareas', function (Blueprint $table) {
            $table->dropUnique(['curso_id', 'practica']);
            $table->dropColumn('practica');
        });

        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('tiene_practicas_campo');
        });
    }
};
