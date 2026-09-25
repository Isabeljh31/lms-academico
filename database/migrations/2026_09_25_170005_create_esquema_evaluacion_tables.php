<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ERS RF-54: Esquema de Evaluacion por Curso (uno por curso)
        Schema::create('esquemas_evaluacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->unique()->constrained('cursos')->onDelete('cascade');
            // RF-62: bloquear edicion si el curso esta finalizado
            $table->boolean('bloqueado')->default(false);
            $table->timestamps();
        });

        // ERS RF-55: Rubros de Evaluacion
        Schema::create('rubros_evaluacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esquema_id')->constrained('esquemas_evaluacion')->onDelete('cascade');
            $table->string('nombre', 100);
            $table->unsignedTinyInteger('semana')->nullable();
            // peso porcentual (0-100); suma debe ser exactamente 100 (RF-56, validado en backend)
            $table->decimal('peso', 5, 2);
            $table->unsignedTinyInteger('orden')->default(1);
            $table->timestamps();
        });

        // ERS RF-57: Nota por estudiante x rubro (escala 0-20, null = no registrada)
        Schema::create('notas_rubro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubro_id')->constrained('rubros_evaluacion')->onDelete('cascade');
            $table->foreignId('estudiante_id')->constrained('users')->onDelete('cascade');
            $table->decimal('nota', 4, 1)->nullable();
            // RF-65: quien registro la nota (para auditoria)
            $table->unsignedBigInteger('registrado_por')->nullable();
            $table->foreign('registrado_por')->references('id')->on('users')->nullOnDelete();
            $table->unique(['rubro_id', 'estudiante_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_rubro');
        Schema::dropIfExists('rubros_evaluacion');
        Schema::dropIfExists('esquemas_evaluacion');
    }
};
