<?php

namespace App\Livewire\Reportes;

use App\Models\Curso;
use App\Models\NotaRubro;
use App\Services\Reportes\ProgresoAcademicoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(ProgresoAcademicoService $servicio)
    {
        $usuario = auth()->user();
        $notaMinima = config('app.nota_minima_aprobatoria', 10.5);

        if ($usuario->hasRole('estudiante')) {
            // ERS RF-59: mostrar nota por rubros + nota ponderada final
            $cursos = Curso::whereIn('id',
                $usuario->inscripciones()->where('activo_local', true)->pluck('curso_id')
            )->get();

            $filas = $cursos->map(function (Curso $curso) use ($servicio, $usuario, $notaMinima) {
                $legacy    = $servicio->progresoEstudiante($usuario, $curso);
                $rubros    = $servicio->resumenRubros($usuario, $curso);

                return array_merge($legacy, [
                    'nota_rubros'    => $rubros['nota_final'],
                    'aprobado'       => $rubros['nota_final'] !== null && $rubros['nota_final'] >= $notaMinima,
                    'tiene_esquema'  => $rubros['esquema'] !== null && $rubros['rubros']->isNotEmpty(),
                ]);
            });

            // Promedio general por rubros (prioridad), si no hay esquema usa tareas
            $notasFinales = $filas->pluck('nota_rubros')->filter(fn ($v) => $v !== null);
            $promedioGeneral = $notasFinales->isNotEmpty()
                ? round($notasFinales->avg(), 1)
                : null;

            // Fallback: promedio legacy si no hay rubros
            if ($promedioGeneral === null) {
                $promediosPorCurso = $filas
                    ->map(fn (array $fila) => collect([$fila['promedio_tareas'], $fila['promedio_quizzes']])
                        ->filter(fn ($v) => $v !== null))
                    ->filter(fn ($valores) => $valores->isNotEmpty())
                    ->map(fn ($valores) => $valores->avg());
                $promedioGeneral = $promediosPorCurso->isNotEmpty()
                    ? round($promediosPorCurso->avg(), 1)
                    : null;
            }

            return view('livewire.reportes.dashboard-estudiante', [
                'filas'           => $filas,
                'promedioGeneral' => $promedioGeneral,
                'notaMinima'      => $notaMinima,
            ]);
        }

        // Profesor / Supervisor
        $cursos = $usuario->hasRole('profesor')
            ? $usuario->cursosComoProfesor()->get()
            : Curso::all();

        $filas = $cursos->map(fn (Curso $curso) => $servicio->resumenCurso($curso));

        // Para Supervisor: agregar métricas de aprobación por rubros
        $metricasRubros = null;
        if ($usuario->hasRole('supervisor')) {
            $totalEstudiantes = 0;
            $totalAprobados   = 0;
            $cursosSinEsquema = 0;

            foreach ($cursos as $curso) {
                if (!$curso->esquemaEvaluacion?->rubros->isNotEmpty()) {
                    $cursosSinEsquema++;
                    continue;
                }
                $inscripcionesActivas = $curso->inscripcionesActivas()->pluck('estudiante_id');
                foreach ($inscripcionesActivas as $estudianteId) {
                    $notaFinal = $servicio->notaFinalPorRubros(
                        \App\Models\User::find($estudianteId),
                        $curso
                    );
                    if ($notaFinal !== null) {
                        $totalEstudiantes++;
                        if ($notaFinal >= $notaMinima) $totalAprobados++;
                    }
                }
            }

            $metricasRubros = [
                'tasa_aprobacion'  => $totalEstudiantes > 0
                    ? round(($totalAprobados / $totalEstudiantes) * 100) : 0,
                'cursos_sin_esquema' => $cursosSinEsquema,
                'cursos_total'     => $cursos->count(),
            ];
        }

        return view('livewire.reportes.dashboard-profesor', [
            'filas'          => $filas,
            'metricasRubros' => $metricasRubros,
        ]);
    }
}
