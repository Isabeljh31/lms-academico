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
        Schema::table('tareas', function (Blueprint $table) {
            // 'individual' o 'grupal'. Validado en la aplicación (mismo
            // criterio que 'estado' en entregas), no como enum de base de datos.
            $table->string('tipo_entrega', 20)->default('individual')->after('peso_nota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropColumn('tipo_entrega');
        });
    }
};
