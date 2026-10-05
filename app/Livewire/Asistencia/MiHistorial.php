<?php

namespace App\Livewire\Asistencia;

use App\Models\Curso;
use App\Services\Asistencia\CalculadoraAsistencia;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ERS RF-72: el estudiante consulta su propio historial de asistencia en un
 * curso, con su porcentaje acumulado (RF-71). Solo ve sus propios registros.
 */
#[Layout('layouts.app')]
class MiHistorial extends Component
{
    public Curso $curso;

    public function mount(Curso $curso): void
    {
        abort_unless(auth()->user()->hasRole('estudiante'), 403);
        Gate::authorize('ver', $curso);

        $this->curso = $curso;
    }

    public function render(CalculadoraAsistencia $calculadora)
    {
        $historial = $calculadora->historial(auth()->user(), $this->curso);

        return view('livewire.asistencia.mi-historial', [
            'historial' => $historial,
            'porcentaje' => $calculadora->porcentaje($historial->pluck('estado')),
            'conteo' => $historial->countBy('estado'),
        ])->title("Mi asistencia — {$this->curso->nombre}");
    }
}
