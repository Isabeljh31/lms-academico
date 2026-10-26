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
     * Datos adicionales del perfil de profesor (celular, año de nacimiento,
     * correo personal de respaldo, CV y datos profesionales). Quedan en la
     * tabla de usuarios, igual que "especialidad", porque son texto libre
     * y no necesitan una tabla aparte todavía.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('celular', 9)->nullable()->after('especialidad');
            $table->unsignedSmallInteger('anio_nacimiento')->nullable()->after('celular');
            $table->string('correo_personal')->nullable()->after('email');
            $table->string('grado_academico')->nullable()->after('especialidad');
            $table->string('colegiatura')->nullable()->after('grado_academico');
            $table->date('fecha_inicio_labores')->nullable()->after('colegiatura');
            $table->string('cv_path')->nullable()->after('fecha_inicio_labores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['celular', 'anio_nacimiento', 'correo_personal', 'grado_academico', 'colegiatura', 'fecha_inicio_labores', 'cv_path']);
        });
    }
};
