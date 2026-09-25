<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            // Permite al profesor preparar una tarea con anticipación y
            // publicarla cuando quiera, en vez de crearla en el momento.
            // default(true) para que las tareas ya existentes sigan
            // visibles tal como estaban antes de esta migración.
            $table->boolean('visible')->default(true)->after('peso_nota');
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropColumn('visible');
        });
    }
};
