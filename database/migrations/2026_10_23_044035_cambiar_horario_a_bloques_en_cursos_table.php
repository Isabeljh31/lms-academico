<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Un solo horario/aula para todos los días elegidos no alcanza: lunes y
     * viernes pueden tener horas distintas, y si es presencial, aulas
     * distintas. Se reemplaza por una lista de bloques independientes
     * (día, hora de inicio, hora de fin, aula), uno por cada día de clase.
     */
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['aula', 'horario_dias', 'horario_hora', 'horario_duracion_minutos']);
            $table->json('horario_bloques')->nullable()->after('modalidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('horario_bloques');
            $table->string('aula')->nullable()->after('modalidad');
            $table->json('horario_dias')->nullable()->after('aula');
            $table->time('horario_hora')->nullable()->after('horario_dias');
            $table->unsignedSmallInteger('horario_duracion_minutos')->nullable()->after('horario_hora');
        });
    }
};
