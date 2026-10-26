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
        Schema::table('cursos', function (Blueprint $table) {
            // presencial (con aula) | virtual (asíncrono, sin horario) | remoto (en vivo, por videollamada).
            $table->string('modalidad', 20)->default('presencial')->after('cupo_maximo');
            $table->string('aula')->nullable()->after('modalidad');
            // Horario semanal (presencial/remoto): días ISO 1=lunes..7=domingo, para generar las sesiones de clase.
            $table->json('horario_dias')->nullable()->after('aula');
            $table->time('horario_hora')->nullable()->after('horario_dias');
            $table->unsignedSmallInteger('horario_duracion_minutos')->nullable()->after('horario_hora');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['modalidad', 'aula', 'horario_dias', 'horario_hora', 'horario_duracion_minutos']);
        });
    }
};
