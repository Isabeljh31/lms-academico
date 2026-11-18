<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facultades (extensión fuera del ERS): catálogo del Supervisor, cada una
 * dentro de una sede, y la facultad de cada curso (cursos.facultad_id,
 * nullable). No toca filas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facultades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->string('codigo', 15)->unique();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['sede_id', 'activa']);
        });

        Schema::table('cursos', function (Blueprint $table) {
            $table->foreignId('facultad_id')->nullable()->constrained('facultades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facultad_id');
        });

        Schema::dropIfExists('facultades');
    }
};
