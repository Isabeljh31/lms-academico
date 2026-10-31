<?php

namespace App\Livewire\Estudiante;

use App\Models\Curso;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Reportes\ProgresoAcademicoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Resumen de calificaciones de todos los cursos matriculados del estudiante
 * (extensión fuera del ERS, equivalente a "Mis calificaciones" de Blackboard):
 * nota final por rubros de cada curso y, dentro de cada curso, el detalle de
 * tareas y quizzes calificados. Cada estudiante solo ve sus propios cursos y
 * notas; el cálculo reutiliza ProgresoAcademicoService::resumenRubros() y
 * ::progresoEstudiante(), las mismas fuentes que usan la Boleta y "Mi
 * progreso", para no duplicar la fórmula. Las notas de práctica (tareas y
 * quizzes) se muestran solo como detalle y nunca se mezclan con la nota final
 * ponderada por rubros (RF-48).
 */
#[Layout('layouts.app')]
class MisCalificaciones extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('estudiante'), 403, 'Esta pantalla es personal de cada estudiante.');
    }

    public function render(ProgresoAcademicoService $progreso, CalculadoraNotas $calculadora)
    {
        $usuario = auth()->user();

        $cursos = Curso::query()
            ->visiblesParaEstudiantes()
            ->whereHas('inscripcionesActivas', fn ($q) => $q->where('estudiante_id', $usuario->id))
            ->with('profesor')
            ->orderBy('nombre')
            ->get();

        $filas = $cursos->map(function (Curso $curso) use ($usuario, $progreso) {
            $resumen = $progreso->resumenRubros($usuario, $curso);
            $practica = $progreso->progresoEstudiante($usuario, $curso);

            return [
                'curso' => $curso,
                'notaFinal' => $resumen['nota_final'],
                'condicion' => $resumen['condicion'],
                'tieneEsquema' => $resumen['rubros']->isNotEmpty(),
                'tareas' => $practica['tareas']->filter(fn ($t) => $t['nota'] !== null)->values(),
                'quizzes' => $practica['quizzes']->filter(fn ($q) => $q['puntaje'] !== null)->values(),
            ];
        });

        $notasFinales = $filas->pluck('notaFinal')->filter(fn ($nota) => $nota !== null);

        return view('livewire.estudiante.mis-calificaciones', [
            'filas' => $filas,
            'promedioGeneral' => $notasFinales->isNotEmpty() ? round($notasFinales->avg(), 1) : null,
            'totalAprobados' => $filas->where('condicion', 'aprobado')->count(),
            'totalDesaprobados' => $filas->where('condicion', 'desaprobado')->count(),
            'totalEnCurso' => $filas->where('condicion', 'en_curso')->count(),
            'notaMinima' => $calculadora->notaMinima(),
        ])->title('Mis Calificaciones - LearnUp');
    }
}
