<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            // ERS RF-40: campos faltantes
            $table->text('instrucciones')->nullable()->after('descripcion');
            $table->timestamp('fecha_inicio')->nullable()->after('instrucciones');
            $table->unsignedTinyInteger('intentos_permitidos')->default(1)->after('peso_nota');
            $table->jsonb('formatos_permitidos')->nullable()->after('intentos_permitidos');
            // ERS RF-37: estado de contenido (reemplaza logica de visible bool)
            // estados: borrador | publicado | programado
            $table->string('estado_contenido', 20)->default('publicado')->after('visible');
            $table->timestamp('publicar_en')->nullable()->after('estado_contenido');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            // ERS RF-45: configuracion extendida
            // {tiempo_limite_min, intentos, aleatorizar, retroalimentacion, puntaje_por_pregunta}
            $table->jsonb('configuracion')->nullable()->after('preguntas');
            $table->timestamp('fecha_inicio')->nullable()->after('configuracion');
            $table->timestamp('fecha_limite')->nullable()->after('fecha_inicio');
            // ERS RF-37
            $table->string('estado_contenido', 20)->default('publicado')->after('visible');
            $table->timestamp('publicar_en')->nullable()->after('estado_contenido');
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropColumn(['instrucciones', 'fecha_inicio', 'intentos_permitidos',
                                'formatos_permitidos', 'estado_contenido', 'publicar_en']);
        });
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['configuracion', 'fecha_inicio', 'fecha_limite',
                                'estado_contenido', 'publicar_en']);
        });
    }
};
