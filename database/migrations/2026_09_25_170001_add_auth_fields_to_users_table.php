<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ERS RF-03, RF-05, RF-06: autenticacion mejorada
            $table->boolean('activo')->default(true)->after('avatar_url');
            $table->boolean('primer_login')->default(true)->after('activo');
            $table->timestamp('bloqueado_hasta')->nullable()->after('primer_login');
            $table->unsignedTinyInteger('intentos_fallidos')->default(0)->after('bloqueado_hasta');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['activo', 'primer_login', 'bloqueado_hasta', 'intentos_fallidos']);
        });
    }
};
