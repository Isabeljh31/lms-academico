<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscripciones', function (Blueprint $table) {
            // ERS RF-28, RF-29, RF-30, RF-32: matricula mejorada
            // origen: manual | api
            $table->string('origen', 20)->default('manual')->after('fecha_inscripcion');
            // estado: activo | inactivo (baja logica, conserva historial)
            $table->string('estado', 20)->default('activo')->after('origen');
            // activo_local: false cuando Supervisor desactiva acceso de matricula por API
            $table->boolean('activo_local')->default(true)->after('estado');
            $table->timestamp('fecha_baja')->nullable()->after('activo_local');
            $table->unsignedBigInteger('dado_de_baja_por')->nullable()->after('fecha_baja');
            $table->foreign('dado_de_baja_por')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->dropForeign(['dado_de_baja_por']);
            $table->dropColumn(['origen', 'estado', 'activo_local', 'fecha_baja', 'dado_de_baja_por']);
        });
    }
};
