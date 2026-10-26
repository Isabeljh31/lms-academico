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
     * Código único por cuenta (prefijo según rol + año de creación + secuencia,
     * p. ej. "P-26-001"), igual patrón que el código de curso. Se genera solo
     * para cuentas nuevas; las que ya existían se quedan sin código.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('codigo')->nullable()->unique()->after('dni');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });
    }
};
