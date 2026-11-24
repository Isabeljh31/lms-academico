<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitud de cambio de clase (extensión fuera del ERS): el Profesor pide
 * reprogramar una clase o dictarla en remoto y el Supervisor decide. Solo
 * agrega estructura: la tabla nueva y la columna nullable
 * sesiones_clase.modalidad (null = la del curso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones_clase', function (Blueprint $table) {
            // presencial | remoto; null = hereda la modalidad del curso.
            $table->string('modalidad', 20)->nullable();
        });

        Schema::create('solicitudes_cambio_clase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones_clase')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            // reprogramar | remoto
            $table->string('tipo', 20);
            // salud | personal | academico | otro
            $table->string('motivo', 20);
            $table->string('detalle', 500)->nullable();
            // presencial | remoto
            $table->string('modalidad_solicitada', 20);
            $table->dateTime('fecha_propuesta')->nullable();
            // Clase tal como estaba al pedir el cambio.
            $table->dateTime('fecha_original');
            $table->unsignedSmallInteger('duracion_original');
            // pendiente | aprobada | rechazada | cancelada
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decidido_en')->nullable();
            $table->text('comentario_decision')->nullable();
            // Lo que aprobó el Supervisor.
            $table->dateTime('fecha_nueva')->nullable();
            $table->unsignedSmallInteger('duracion_nueva')->nullable();
            $table->string('modalidad_nueva', 20)->nullable();
            $table->string('aula_nueva', 50)->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index('sesion_id');
        });

        // Como máximo una solicitud pendiente por clase (índice único parcial; PostgreSQL y SQLite).
        DB::statement("CREATE UNIQUE INDEX solicitudes_cambio_clase_una_pendiente_unique ON solicitudes_cambio_clase (sesion_id) WHERE estado = 'pendiente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cambio_clase');

        Schema::table('sesiones_clase', function (Blueprint $table) {
            $table->dropColumn('modalidad');
        });
    }
};
