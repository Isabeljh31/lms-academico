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
        Schema::create('propuestas_bot', function (Blueprint $table) {
        $table->id();
        $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
        $table->string('tipo', 50);
        $table->jsonb('datos_propuesta');
        $table->string('estado', 20)->default('pendiente');
        $table->foreignId('aprobado_por')->nullable()->constrained('users');
        $table->timestamp('fecha_respuesta')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('propuestas_bot');
    }
};
