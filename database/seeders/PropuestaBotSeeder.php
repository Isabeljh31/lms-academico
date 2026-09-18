<?php

namespace Database\Seeders;

use App\Models\PropuestaBot;
use App\Models\User;
use App\Services\Bot\PropuestaBotService;
use Illuminate\Database\Seeder;

class PropuestaBotSeeder extends Seeder
{
    public function run(): void
    {
        // Genera la(s) propuesta(s) reales a partir de las tareas atrasadas
        // sembradas por CursoSeeder (mismo camino de código que el scheduler).
        app(PropuestaBotService::class)->detectarAtrasos();

        $estudiante = User::where('email', 'estudiante@lms.edu.pe')->first();

        if (! $estudiante) {
            return;
        }

        // Además, un par de propuestas ya resueltas (una aceptada, una
        // rechazada) para poder ver los tres estados en la bandeja de una.
        PropuestaBot::firstOrCreate([
            'usuario_id' => $estudiante->id,
            'tipo' => 'redistribucion_plan',
            'estado' => 'aprobado',
        ], [
            'datos_propuesta' => [
                'tarea_id' => 0,
                'tarea_titulo' => 'Tarea de ejemplo ya aceptada',
                'curso_id' => 0,
                'curso_nombre' => 'Historia del Perú',
                'motivo' => 'Ejemplo de propuesta ya aceptada por el estudiante.',
                'nueva_fecha_recordatorio' => now()->subDays(2)->toIso8601String(),
            ],
            'aprobado_por' => $estudiante->id,
            'fecha_respuesta' => now()->subDays(2),
        ]);

        PropuestaBot::firstOrCreate([
            'usuario_id' => $estudiante->id,
            'tipo' => 'redistribucion_plan',
            'estado' => 'rechazado',
        ], [
            'datos_propuesta' => [
                'tarea_id' => 0,
                'tarea_titulo' => 'Tarea de ejemplo ya rechazada',
                'curso_id' => 0,
                'curso_nombre' => 'Introducción a la Programación',
                'motivo' => 'Ejemplo de propuesta ya rechazada por el estudiante.',
                'nueva_fecha_recordatorio' => now()->subDays(1)->toIso8601String(),
            ],
            'aprobado_por' => $estudiante->id,
            'fecha_respuesta' => now()->subDays(1),
        ]);
    }
}
