<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            // ERS RF-19, RF-22: fechas, duracion, sesiones y estado de publicacion
            // estados: borrador | publicado | despublicado | archivado
            $table->string('estado', 20)->default('borrador')->after('imagen_portada_url');
            $table->date('fecha_inicio')->nullable()->after('estado');
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            $table->unsignedSmallInteger('semanas_duracion')->nullable()->after('fecha_fin');
            $table->unsignedSmallInteger('sesiones_clase_count')->nullable()->after('semanas_duracion');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['estado', 'fecha_inicio', 'fecha_fin', 'semanas_duracion', 'sesiones_clase_count']);
        });
    }
};
