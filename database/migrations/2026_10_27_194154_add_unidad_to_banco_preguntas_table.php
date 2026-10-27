<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unidad de cada pregunta del banco (T1 a T4, BancoPregunta::UNIDADES), para
 * filtrar por unidad; "tema" pasa a ser el contenido dentro de la unidad.
 * Nullable y sin backfill: las preguntas existentes se muestran "Sin unidad".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banco_preguntas', function (Blueprint $table) {
            $table->string('unidad', 10)->nullable();
            $table->index(['curso_id', 'unidad']);
        });
    }

    public function down(): void
    {
        Schema::table('banco_preguntas', function (Blueprint $table) {
            $table->dropIndex(['curso_id', 'unidad']);
            $table->dropColumn('unidad');
        });
    }
};
