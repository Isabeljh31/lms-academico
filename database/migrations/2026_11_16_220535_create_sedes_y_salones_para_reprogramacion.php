<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reprogramación de clases con salones reales (extensión fuera del ERS).
 * Solo agrega estructura: el catálogo sedes / tipos_salon / salones, las
 * columnas nullable cursos.sede_id y sesiones_clase.salon_id, y columnas
 * nullable en solicitudes_cambio_clase para la aprobación automática, el
 * sustento y la reversión. No toca filas existentes: las clases antiguas
 * conservan su aula en texto («Aula: X») y salon_id queda en null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('codigo', 10)->unique();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('tipos_salon', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('salones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 100);
            $table->foreignId('tipo_salon_id')->constrained('tipos_salon')->restrictOnDelete();
            $table->unsignedSmallInteger('capacidad');
            $table->text('equipamiento')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['sede_id', 'tipo_salon_id', 'activo']);
        });

        Schema::table('cursos', function (Blueprint $table) {
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
        });

        Schema::table('sesiones_clase', function (Blueprint $table) {
            $table->foreignId('salon_id')->nullable()->constrained('salones')->nullOnDelete();
            $table->index(['salon_id', 'fecha']);
        });

        Schema::table('solicitudes_cambio_clase', function (Blueprint $table) {
            // automatica | supervisor (quién aplicó la reprogramación aprobada).
            $table->string('origen', 20)->nullable();
            // Clase tal como estaba (para revertir).
            $table->foreignId('salon_original_id')->nullable()->constrained('salones')->nullOnDelete();
            $table->text('descripcion_original')->nullable();
            $table->string('modalidad_original', 20)->nullable();
            $table->foreignId('salon_nuevo_id')->nullable()->constrained('salones')->nullOnDelete();
            // Por qué quedó en revisión (motivo técnico, no el del Profesor).
            $table->string('razon_revision', 255)->nullable();
            // Sustento que pide el Supervisor y la respuesta del Profesor.
            $table->text('sustento_pedido')->nullable();
            $table->dateTime('sustento_pedido_en')->nullable();
            $table->text('sustento_respuesta')->nullable();
            $table->text('sustento_archivo')->nullable();
            $table->dateTime('sustento_respondido_en')->nullable();
            // Reversión de una reprogramación aplicada.
            $table->foreignId('revertida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revertida_en')->nullable();
            $table->text('comentario_reversion')->nullable();
        });

        $this->restaurarIndiceParcialEnSqlite();
    }

    public function down(): void
    {
        Schema::table('solicitudes_cambio_clase', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salon_original_id');
            $table->dropConstrainedForeignId('salon_nuevo_id');
            $table->dropConstrainedForeignId('revertida_por');
            $table->dropColumn([
                'origen', 'descripcion_original', 'modalidad_original', 'razon_revision', 'sustento_pedido',
                'sustento_pedido_en', 'sustento_respuesta', 'sustento_archivo', 'sustento_respondido_en',
                'revertida_en', 'comentario_reversion',
            ]);
        });

        $this->restaurarIndiceParcialEnSqlite();

        Schema::table('sesiones_clase', function (Blueprint $table) {
            $table->dropIndex(['salon_id', 'fecha']);
            $table->dropConstrainedForeignId('salon_id');
        });

        Schema::table('cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_id');
        });

        Schema::dropIfExists('salones');
        Schema::dropIfExists('tipos_salon');
        Schema::dropIfExists('sedes');
    }

    /**
     * SQLite reconstruye la tabla al agregar o quitar llaves foráneas y el
     * índice único parcial («una pendiente por clase») pierde su WHERE. Se
     * vuelve a crear igual que en la migración original. En PostgreSQL el
     * ALTER no toca ese índice, así que no se hace nada.
     */
    protected function restaurarIndiceParcialEnSqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS solicitudes_cambio_clase_una_pendiente_unique');
        DB::statement("CREATE UNIQUE INDEX solicitudes_cambio_clase_una_pendiente_unique ON solicitudes_cambio_clase (sesion_id) WHERE estado = 'pendiente'");
    }
};
