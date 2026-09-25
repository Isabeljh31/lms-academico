<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacoras_auditoria', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ejecutado_por')->nullable();
            $table->foreign('ejecutado_por')->references('id')->on('users')->nullOnDelete();
            // Tipos: cambio_nota | baja_estudiante | creacion_cuenta | desactivacion_cuenta
            //        matricula_manual | modificacion_esquema | otro_admin
            $table->string('tipo_evento', 50);
            $table->unsignedBigInteger('curso_id')->nullable();
            $table->foreign('curso_id')->references('id')->on('cursos')->nullOnDelete();
            $table->unsignedBigInteger('usuario_afectado_id')->nullable();
            $table->foreign('usuario_afectado_id')->references('id')->on('users')->nullOnDelete();
            // Entidad afectada (ej. nota_rubro, inscripcion)
            $table->string('entidad', 100)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->text('motivo')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('ip', 45)->nullable();
            // Solo created_at: registro inmutable (RF-99)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacoras_auditoria');
    }
};
