<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los modelos ya guardan el correo en minúsculas y el código de curso en
     * mayúsculas; esta migración alinea los registros creados antes del cambio.
     * Si dos cuentas o dos cursos quedaran repetidos al normalizar, se detiene
     * sin modificar nada para que el equipo resuelva el duplicado a mano.
     */
    public function up(): void
    {
        $correosRepetidos = DB::table('users')
            ->selectRaw('lower(trim(email)) as correo')
            ->groupByRaw('lower(trim(email))')
            ->havingRaw('count(*) > 1')
            ->pluck('correo');

        $codigosRepetidos = DB::table('cursos')
            ->whereNotNull('codigo')
            ->selectRaw('upper(trim(codigo)) as codigo')
            ->groupByRaw('upper(trim(codigo))')
            ->havingRaw('count(*) > 1')
            ->pluck('codigo');

        if ($correosRepetidos->isNotEmpty() || $codigosRepetidos->isNotEmpty()) {
            throw new RuntimeException(
                'No se puede normalizar: hay correos ('.$correosRepetidos->implode(', ').') o códigos de curso ('
                .$codigosRepetidos->implode(', ').') que solo se diferencian en mayúsculas. Unifícalos antes de migrar.'
            );
        }

        DB::table('users')->whereRaw('email <> lower(trim(email))')->update(['email' => DB::raw('lower(trim(email))')]);
        DB::table('cursos')->whereNotNull('codigo')->whereRaw('codigo <> upper(trim(codigo))')->update(['codigo' => DB::raw('upper(trim(codigo))')]);
    }

    /**
     * No es reversible: no se conserva cómo estaba escrito cada valor.
     */
    public function down(): void
    {
        //
    }
};
