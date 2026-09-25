<?php

namespace App\Livewire\Admin\Metricas;

use App\Models\Curso;
use App\Models\User;
use App\Models\Asistencia;
use App\Services\Reportes\ProgresoAcademicoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
    }

    public function render(ProgresoAcademicoService $servicio)
    {
        // 1. Analítica de Riesgo de Abandono
        $estudiantesEnRiesgo = collect();
        $estudiantes = User::role('estudiante')->where('activo', true)->get();

        foreach ($estudiantes as $estudiante) {
            $inscripciones = $estudiante->inscripciones()->where('activo_local', true)->get();
            if ($inscripciones->isEmpty()) continue;

            $cursosCriticos = 0;
            $motivos = [];

            foreach ($inscripciones as $inscripcion) {
                $curso = $inscripcion->curso;
                
                // Riesgo por Asistencia
                $sesionesTotales = $curso->sesionesClase()->count();
                if ($sesionesTotales > 0) {
                    $asistencias = Asistencia::where('estudiante_id', $estudiante->id)
                        ->whereHas('sesion', fn($q) => $q->where('curso_id', $curso->id))
                        ->whereIn('estado', ['presente', 'tardanza'])
                        ->count();
                    $pctAsistencia = ($asistencias / $sesionesTotales) * 100;
                    if ($pctAsistencia < 50) {
                        $cursosCriticos++;
                        $motivos[] = "Baja asistencia en {$curso->nombre} (" . round($pctAsistencia) . "%)";
                    }
                }

                // Riesgo por Notas
                $notaFinal = $servicio->notaFinalPorRubros($estudiante, $curso);
                if ($notaFinal !== null && $notaFinal < 10) {
                    $cursosCriticos++;
                    $motivos[] = "Promedio bajo en {$curso->nombre} ({$notaFinal})";
                }
            }

            if ($cursosCriticos > 0) {
                $estudiantesEnRiesgo->push([
                    'estudiante' => $estudiante,
                    'nivel' => $cursosCriticos > 2 ? 'Alto' : 'Medio',
                    'motivos' => array_unique($motivos)
                ]);
            }
        }

        // 2. Métricas de Engagement Global
        $totalCursos = Curso::count();
        $totalEstudiantes = $estudiantes->count();
        $inscripcionesActivas = \App\Models\Inscripcion::where('activo_local', true)->count();
        $promedioInscripciones = $totalEstudiantes > 0 ? round($inscripcionesActivas / $totalEstudiantes, 1) : 0;
        
        $totalMensajes = \App\Models\Mensaje::count();
        $totalInteraccionesIA = \App\Models\ConversacionBot::count(); // Cantidad de conversaciones iniciadas

        return view('livewire.admin.metricas.index', [
            'estudiantesEnRiesgo' => $estudiantesEnRiesgo->sortByDesc('nivel'),
            'totalCursos' => $totalCursos,
            'totalEstudiantes' => $totalEstudiantes,
            'promedioInscripciones' => $promedioInscripciones,
            'totalMensajes' => $totalMensajes,
            'totalInteraccionesIA' => $totalInteraccionesIA,
        ])->title('Métricas y Analítica - Supervisor');
    }
}
