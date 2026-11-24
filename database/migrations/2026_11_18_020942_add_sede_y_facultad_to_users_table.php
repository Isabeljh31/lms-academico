<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sede y facultad propias del estudiante (nullable). Además de las de sus
 * cursos presenciales, ubican al estudiante en los listados del Supervisor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('facultad_id')->nullable()->constrained('facultades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('facultad_id');
            $table->dropConstrainedForeignId('sede_id');
        });
    }
};
