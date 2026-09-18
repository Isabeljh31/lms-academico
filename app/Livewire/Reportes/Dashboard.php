<?php

namespace App\Livewire\Reportes;

use App\Models\Curso;
use App\Services\Reportes\ProgresoAcademicoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(ProgresoAcademicoService $servicio)
    {
        $usuario = auth()->user();

        if ($usuario->hasRole('estudiante')) {
            $cursos = Curso::whereIn('id', $usuario->inscripciones()->pluck('curso_id'))->get();
            $filas = $cursos->map(fn (Curso $curso) => $servicio->progresoEstudiante($usuario, $curso));

            // Promedio general (Módulo 9, ampliación): combina el promedio de
            // cada curso (mismo criterio que ya usaba la fila expandible,
            // tareas+quizzes) en una sola cifra entre los cursos inscritos.
            $promediosPorCurso = $filas
                ->map(fn (array $fila) => collect([$fila['promedio_tareas'], $fila['promedio_quizzes']])
                    ->filter(fn ($v) => $v !== null))
                ->filter(fn ($valores) => $valores->isNotEmpty())
                ->map(fn ($valores) => $valores->avg());

            $promedioGeneral = $promediosPorCurso->isNotEmpty() ? round($promediosPorCurso->avg(), 1) : null;

            return view('livewire.reportes.dashboard-estudiante', [
                'filas' => $filas,
                'promedioGeneral' => $promedioGeneral,
            ]);
        }

        $cursos = $usuario->hasRole('profesor')
            ? $usuario->cursosComoProfesor()->get()
            : Curso::all();

        $filas = $cursos->map(fn (Curso $curso) => $servicio->resumenCurso($curso));

        return view('livewire.reportes.dashboard-profesor', ['filas' => $filas]);
    }
}
