<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlace de reunión (Teams, Meet, Zoom...) para las clases remotas, sin
 * integrar ningún servicio: el Profesor pega un enlace que ya tiene. Cambio
 * solo aditivo (columnas nullable). Al leer, el enlace de la clase tiene
 * prioridad y, si no hay, se usa el del curso (no se copia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->string('enlace_remoto', 500)->nullable();
        });

        Schema::table('sesiones_clase', function (Blueprint $table) {
            $table->string('enlace_remoto', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sesiones_clase', function (Blueprint $table) {
            $table->dropColumn('enlace_remoto');
        });

        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('enlace_remoto');
        });
    }
};
