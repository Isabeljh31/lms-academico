<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Debates del curso (extensión fuera del ERS, inspirada en la pestaña
     * Debates de Blackboard): el Profesor publica una consigna y el curso
     * comenta. Reutiliza los estados de publicación borrador | publicado;
     * "cerrado_en" deja el debate en solo lectura. Aditiva: no toca tablas
     * existentes.
     */
    public function up(): void
    {
        Schema::create('debates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            // Si se borra la cuenta del autor, el debate y su conversación se conservan.
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo', 150);
            $table->text('consigna');
            $table->string('estado', 20)->default('borrador');
            $table->timestamp('publicado_en')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();

            $table->index(['curso_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debates');
    }
};
