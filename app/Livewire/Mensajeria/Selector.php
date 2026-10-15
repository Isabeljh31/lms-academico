<?php

namespace App\Livewire\Mensajeria;

use App\Models\Curso;
use App\Models\Mensaje;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ERS RF-80 a RF-83: punto de entrada a la mensajería desde el sidebar.
 * Cada curso tiene su propia bandeja (cursos.mensajeria); aquí el usuario
 * elige a cuál entrar. La matriz de permisos no da mensajería al Supervisor
 * (ruta con role:estudiante|profesor).
 */
#[Layout('layouts.app')]
class Selector extends Component
{
    public function render()
    {
        $usuario = auth()->user();

        $cursos = $usuario->hasRole('profesor')
            ? Curso::dictadosPor($usuario)->orderBy('nombre')->get()
            : Curso::whereHas('inscripcionesActivas', fn ($q) => $q->where('estudiante_id', $usuario->id))
                ->visiblesParaEstudiantes()->orderBy('nombre')->get();

        $noLeidosPorCurso = Mensaje::where('destinatario_id', $usuario->id)
            ->whereNull('leido_at')
            ->whereIn('curso_id', $cursos->pluck('id'))
            ->selectRaw('curso_id, count(*) as total')
            ->groupBy('curso_id')
            ->pluck('total', 'curso_id');

        return view('livewire.mensajeria.selector', [
            'cursos' => $cursos,
            'noLeidosPorCurso' => $noLeidosPorCurso,
        ])->title('Mensajería — LearnUp');
    }
}
