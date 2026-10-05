<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas aditivas para completar requisitos del ERS v2. No se elimina
     * ninguna columna existente; el único cambio de columna es volver
     * `entregas.archivo_url` opcional (RF-42: la entrega puede ser solo texto
     * o enlace), lo que no altera datos existentes.
     */
    public function up(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            // RF-37: Borrador | Publicado | Programado también para Módulos.
            // Por defecto "publicado" para no ocultar módulos ya existentes.
            $table->string('estado_contenido', 20)->default('publicado')->after('orden');
            $table->timestamp('publicar_en')->nullable()->after('estado_contenido');
        });

        Schema::table('cursos', function (Blueprint $table) {
            // RF-73: el módulo de asistencia puede omitirse por curso.
            $table->boolean('usa_asistencia')->default(true)->after('sesiones_clase_count');
            // RF-103: el sistema externo puede identificar el curso por código.
            $table->string('codigo', 50)->nullable()->unique()->after('nombre');
        });

        Schema::table('tareas', function (Blueprint $table) {
            // RF-76: tarea grupal asignada a un grupo específico (null = todos los grupos).
            $table->foreignId('grupo_asignado_id')->nullable()->after('tipo_entrega')
                ->constrained('grupos')->nullOnDelete();
        });

        Schema::table('entregas', function (Blueprint $table) {
            $table->text('archivo_url')->nullable()->change();
            // RF-42: respuesta escrita y/o enlaces.
            $table->text('respuesta_texto')->nullable()->after('archivo_url');
            $table->string('enlace', 500)->nullable()->after('respuesta_texto');
            // RF-40: intentos de entrega consumidos.
            $table->unsignedTinyInteger('intentos_usados')->default(1)->after('estado');
        });

        Schema::table('resultados_quiz', function (Blueprint $table) {
            // RF-45: varios intentos por quiz.
            $table->unsignedTinyInteger('intento')->default(1)->after('estudiante_id');
            $table->unsignedInteger('tiempo_usado_seg')->nullable()->after('puntaje');
        });

        Schema::table('comunicados', function (Blueprint $table) {
            // RF-87: fecha y hora de publicación.
            $table->timestamp('publicar_en')->nullable()->after('activo');
            $table->boolean('notificado')->default(false)->after('publicar_en');
        });
    }

    public function down(): void
    {
        Schema::table('comunicados', function (Blueprint $table) {
            $table->dropColumn(['publicar_en', 'notificado']);
        });

        Schema::table('resultados_quiz', function (Blueprint $table) {
            $table->dropColumn(['intento', 'tiempo_usado_seg']);
        });

        Schema::table('entregas', function (Blueprint $table) {
            $table->dropColumn(['respuesta_texto', 'enlace', 'intentos_usados']);
        });

        Schema::table('tareas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grupo_asignado_id');
        });

        Schema::table('cursos', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn(['usa_asistencia', 'codigo']);
        });

        Schema::table('modulos', function (Blueprint $table) {
            $table->dropColumn(['estado_contenido', 'publicar_en']);
        });
    }
};
