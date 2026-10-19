<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contenido organizado por semanas (Semana 1..N del curso) y una sección
 * "Prácticas de campo" fuera de la numeración semanal. El contenedor sigue
 * siendo la tabla `modulos`: cada semana es un módulo con `semana` = N, así la
 * completitud, el progreso y la visibilidad (RF-38/RF-67) no cambian.
 *
 * Migración de datos (sin pérdida: solo se rellenan columnas nuevas y el
 * orden, respetando el orden relativo que ya tenían):
 * - Cada Unidad existente pasa a UNA semana, en el orden en que el Profesor
 *   las tenía. Si el curso tiene duración (semanas_duracion) y no hay más
 *   unidades que semanas, cada unidad va a la semana en que empieza su tramo
 *   del ciclo: con 4 unidades en 16 semanas -> semanas 1, 5, 9 y 13 (coincide
 *   con el vencimiento de sus tareas en los datos reales). Si no hay duración
 *   o hay más unidades que semanas: Unidad k -> Semana k.
 * - Tipo de recurso de cada lección: "video" si tiene video, "lectura" si no.
 *
 * Ver docs/CAMBIOS.md (contenido por semanas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            $table->string('tipo_seccion', 20)->default('semana');
            $table->unsignedSmallInteger('semana')->nullable();
        });

        Schema::table('lecciones', function (Blueprint $table) {
            $table->string('tipo_recurso', 30)->default('lectura');
        });

        DB::table('cursos')->select(['id', 'semanas_duracion'])->orderBy('id')->each(function (object $curso) {
            $modulos = DB::table('modulos')->where('curso_id', $curso->id)->orderBy('orden')->orderBy('id')->pluck('id')->values();
            $unidades = $modulos->count();
            $semanas = (int) $curso->semanas_duracion;

            foreach ($modulos as $indice => $moduloId) {
                $semana = $semanas > 0 && $unidades <= $semanas
                    ? intdiv($indice * $semanas, $unidades) + 1
                    : $indice + 1;

                DB::table('modulos')->where('id', $moduloId)->update(['semana' => $semana, 'orden' => $semana]);
            }
        });

        DB::table('lecciones')->whereNotNull('video_url')->where('video_url', '!=', '')->update(['tipo_recurso' => 'video']);

        Schema::table('modulos', function (Blueprint $table) {
            $table->unique(['curso_id', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            $table->dropUnique(['curso_id', 'semana']);
            $table->dropColumn(['tipo_seccion', 'semana']);
        });

        Schema::table('lecciones', function (Blueprint $table) {
            $table->dropColumn('tipo_recurso');
        });
    }
};
