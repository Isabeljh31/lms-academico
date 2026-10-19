<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERS RF-84: el contenido Programado debe avisar a los estudiantes cuando se
 * publica solo. `aviso_programado_en` marca que ese aviso ya se envió
 * (comando contenido:notificar-programado). Migración aditiva.
 */
return new class extends Migration
{
    /** @var array<string, string> tabla => columna de fecha de publicación */
    protected array $tablas = [
        'modulos' => 'publicar_en',
        'lecciones' => 'fecha_publicacion',
        'tareas' => 'publicar_en',
        'quizzes' => 'publicar_en',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla => $columnaFecha) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->timestamp('aviso_programado_en')->nullable();
            });

            // Lo programado que ya se publicó antes de esta migración no se notifica de forma retroactiva.
            DB::table($tabla)->whereNotNull($columnaFecha)->where($columnaFecha, '<=', now())
                ->update(['aviso_programado_en' => now()]);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tablas) as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('aviso_programado_en');
            });
        }
    }
};
