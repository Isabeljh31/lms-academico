<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ERS RF-101: API Key por institucion/empresa cliente
        Schema::create('integraciones_externas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            // hash bcrypt de la API key (la key real solo se muestra al crearla)
            $table->string('api_key_hash', 255);
            // prefijo visible para identificar la key (ej. LU_abc1xxxx)
            $table->string('api_key_prefix', 12);
            $table->boolean('activo')->default(true);
            $table->text('descripcion')->nullable();
            $table->timestamp('ultimo_uso')->nullable();
            $table->timestamps();
        });

        // ERS RF-106: log de solicitudes entrantes al webhook
        Schema::create('logs_integracion_api', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integracion_id')->nullable();
            $table->foreign('integracion_id')->references('id')->on('integraciones_externas')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('metodo', 10)->default('POST');
            $table->string('endpoint', 255);
            $table->integer('status_code');
            $table->jsonb('payload_recibido')->nullable();
            $table->text('error_mensaje')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_integracion_api');
        Schema::dropIfExists('integraciones_externas');
    }
};
