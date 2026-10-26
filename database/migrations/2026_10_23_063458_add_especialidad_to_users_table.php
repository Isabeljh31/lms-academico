<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Solo tiene sentido para profesores (qué materias/áreas dicta), pero se
     * deja a nivel de usuario en vez de una tabla aparte: es un dato de texto
     * libre, no un catálogo que haya que relacionar o filtrar todavía.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('especialidad')->nullable()->after('dni');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('especialidad');
        });
    }
};
