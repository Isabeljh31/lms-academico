<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERS RF-107 y RF-108: parámetros generales de la plataforma (nota mínima
     * aprobatoria, institución, zona horaria, formato de fecha, bot activo).
     * Antes se guardaban en un JSON en storage/, que se pierde en cada
     * despliegue en contenedores efímeros (Railway).
     */
    public function up(): void
    {
        Schema::create('configuraciones_plataforma', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_plataforma');
    }
};
