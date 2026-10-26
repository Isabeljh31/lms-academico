<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Información del curso": carpetas que arma el Supervisor con archivos y
     * enlaces administrativos (sílabo, actas, etc.), separado del contenido
     * semanal que publica el docente. Cada recurso es {nombre, url}, donde
     * "url" es la ruta de almacenamiento de un archivo subido o un enlace
     * externo (mismo criterio que Modulo::materiales).
     */
    public function up(): void
    {
        Schema::create('carpetas_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->string('nombre');
            $table->unsignedInteger('orden')->default(0);
            $table->json('recursos')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carpetas_curso');
    }
};
