<?php

namespace App\Livewire\Compartido\Reportes;

use App\Models\Curso;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Reportes\ProgresoAcademicoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reportes (ERS 3.14).
 * - Estudiante (RF-59, RF-60, RF-68): progreso, nota final por rubros y
 *   notas de práctica de cada curso matriculado.
 * - Profesor (RF-89): reporte de sus cursos. Supervisor: de todos los cursos.
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(ProgresoAcademicoService $servicio, CalculadoraNotas $calculadora)
    {
        $usuario = auth()->user();
        $notaMinima = $calculadora->notaMinima();

        if ($usuario->hasRole('estudiante')) {
            $cursos = Curso::whereHas('inscripcionesActivas', fn ($q) => $q->where('estudiante_id', $usuario->id))
                ->visiblesParaEstudiantes()
                ->get();

            $filas = $cursos->map(function (Curso $curso) use ($servicio, $usuario) {
                $rubros = $servicio->resumenRubros($usuario, $curso);

                return [
                    ...$servicio->progresoEstudiante($usuario, $curso),
                    'nota_rubros' => $rubros['nota_final'],
                    'condicion' => $rubros['condicion'],
                    'aprobado' => $rubros['aprobado'],
                    'tiene_esquema' => $rubros['rubros']->isNotEmpty(),
                    'avance' => $servicio->avance($usuario, $curso),
                ];
            });

            $notasFinales = $filas->pluck('nota_rubros')->filter(fn ($v) => $v !== null);

            return view('livewire.compartido.reportes.dashboard-estudiante', [
                'filas' => $filas,
                // Solo la nota final ponderada por rubros: las notas de práctica no se mezclan (RF-48).
                'promedioGeneral' => $notasFinales->isNotEmpty() ? round($notasFinales->avg(), 1) : null,
                'notaMinima' => $notaMinima,
            ])->title('Mi progreso — LearnUp');
        }

        $cursos = $usuario->hasRole('profesor')
            ? $usuario->cursosComoDocente()->orderBy('nombre')->get()
            : Curso::orderBy('nombre')->get();

        $filas = $cursos->map(fn (Curso $curso) => $servicio->resumenCurso($curso));

        return view('livewire.compartido.reportes.dashboard-profesor', [
            'filas' => $filas,
            'notaMinima' => $notaMinima,
        ])->title('Reportes — LearnUp');
    }
}
