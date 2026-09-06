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
        Schema::create('inscripciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
        $table->foreignId('estudiante_id')->constrained('users')->onDelete('cascade');
        $table->timestamp('fecha_inscripcion')->useCurrent();
        $table->unique(['curso_id', 'estudiante_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
