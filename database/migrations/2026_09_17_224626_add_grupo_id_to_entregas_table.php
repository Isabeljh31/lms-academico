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
        Schema::table('entregas', function (Blueprint $table) {
            // Nula para tareas individuales. En una tarea grupal, todas las
            // entregas de los integrantes de un mismo grupo comparten este
            // valor — así se puede calificar/mostrar el grupo como una sola
            // unidad sin dejar de tener una fila por estudiante (el resto de
            // la plataforma sigue consultando entregas por estudiante_id
            // exactamente igual que antes).
            $table->foreignId('grupo_id')->nullable()->after('estudiante_id')
                ->constrained('grupos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grupo_id');
        });
    }
};
