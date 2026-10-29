<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comentarios de un debate. "parent_id" apunta al comentario principal
     * cuando es una respuesta (un solo nivel). Aditiva: no toca tablas
     * existentes.
     */
    public function up(): void
    {
        Schema::create('debate_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debate_id')->constrained('debates')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('debate_comentarios')->cascadeOnDelete();
            $table->text('contenido');
            $table->timestamp('editado_en')->nullable();
            $table->timestamps();

            $table->index(['debate_id', 'parent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debate_comentarios');
    }
};
