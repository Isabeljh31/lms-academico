<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERS RF-35 a RF-38: Lecciones dentro de un Módulo. El modelo App\Models\Leccion
     * existía sin tabla, por eso la pantalla de lecciones fallaba.
     */
    public function up(): void
    {
        Schema::create('lecciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->text('material_url')->nullable();
            $table->string('material_nombre')->nullable();
            // borrador | publicado | programado (RF-37)
            $table->string('estado', 20)->default('borrador');
            $table->timestamp('fecha_publicacion')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecciones');
    }
};
