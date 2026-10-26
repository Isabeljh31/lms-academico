<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos del apoderado de un estudiante (nombre y celular de contacto),
     * opcionales porque algunos estudiantes ya son mayores de edad.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('apoderado_nombre', 120)->nullable()->after('correo_personal');
            $table->string('apoderado_celular', 9)->nullable()->after('apoderado_nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['apoderado_nombre', 'apoderado_celular']);
        });
    }
};
