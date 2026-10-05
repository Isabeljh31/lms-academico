<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERS RF-80 a RF-83: mensajería privada. El modelo Mensaje existía sin tabla.
     */
    public function up(): void
    {
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remitente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('destinatario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            $table->string('asunto');
            $table->text('cuerpo');
            $table->text('archivo_url')->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();

            $table->index(['destinatario_id', 'leido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};
