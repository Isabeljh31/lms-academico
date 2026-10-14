<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERS RF-10: la cuenta guarda nombres y apellidos por separado, más el DNI
     * (8 dígitos, único). "name" se conserva como nombre completo calculado,
     * para no cambiar las pantallas que ya lo muestran.
     *
     * Las cuentas existentes se separan así: la primera palabra va a nombres y
     * el resto a apellidos. Si alguna queda mal (p. ej. "María José Pérez"), el
     * Supervisor la corrige desde Usuarios → Editar. El DNI queda vacío hasta
     * que el Supervisor lo registre.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nombres', 60)->nullable()->after('name');
            $table->string('apellidos', 60)->nullable()->after('nombres');
            // Texto y no número: un DNI puede empezar con 0 (p. ej. 01234567).
            $table->char('dni', 8)->nullable()->unique()->after('apellidos');
        });

        DB::table('users')->select(['id', 'name'])->orderBy('id')->each(function (object $usuario) {
            $partes = preg_split('/\s+/u', trim((string) $usuario->name), 2);

            DB::table('users')->where('id', $usuario->id)->update([
                'nombres' => mb_substr($partes[0] ?? '', 0, 60) ?: null,
                'apellidos' => mb_substr($partes[1] ?? '', 0, 60) ?: null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['dni']);
            $table->dropColumn(['nombres', 'apellidos', 'dni']);
        });
    }
};
