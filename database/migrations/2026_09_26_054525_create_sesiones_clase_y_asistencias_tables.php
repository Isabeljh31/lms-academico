<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERS RF-39 y RF-70 a RF-73: Sesiones de Clase y asistencia por estudiante.
     * Los modelos SesionClase y Asistencia existían sin tabla.
     */
    public function up(): void
    {
        Schema::create('sesiones_clase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->string('titulo');
            $table->dateTime('fecha');
            $table->unsignedSmallInteger('duracion_minutos')->default(90);
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('numero_sesion')->default(1);
            $table->timestamps();
        });

        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones_clase')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            // presente | ausente | tardanza | justificado
            $table->string('estado', 20)->default('ausente');
            $table->string('observacion', 500)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sesion_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
        Schema::dropIfExists('sesiones_clase');
    }
};
