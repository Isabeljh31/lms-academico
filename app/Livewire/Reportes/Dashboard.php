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

            return view('livewire.reportes.dashboard-estudiante', ['filas' => $filas]);
        }

        $cursos = $usuario->hasRole('profesor')
            ? $usuario->cursosComoProfesor()->get()
            : Curso::all();

        $filas = $cursos->map(fn (Curso $curso) => $servicio->resumenCurso($curso));

        return view('livewire.reportes.dashboard-profesor', ['filas' => $filas]);
    }
}
