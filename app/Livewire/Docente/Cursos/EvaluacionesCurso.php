<?php

namespace App\Livewire\Docente\Cursos;

use App\Models\Curso;
use App\Services\Evaluacion\AvanceEvaluaciones;
use App\Services\Reportes\PanelProfesor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Tarjeta "Evaluaciones" del detalle del curso del Profesor: las evaluaciones
 * que definió el Supervisor (semana y peso, solo lectura), cuántas
 * actividades tiene cada una y cuántas entregas esperan nota. El estado sale
 * de AvanceEvaluaciones, igual que en la pantalla Actividades.
 *
 * Componente aparte: sus consultas se hacen una sola vez al cargar la página.
 */
class EvaluacionesCurso extends Component
{
    public Curso $curso;

    public function mount(Curso $curso): void
    {
        Gate::authorize('gestionar', $curso);

        $this->curso = $curso;
    }

    public function render(AvanceEvaluaciones $avance, PanelProfesor $panel): View
    {
        $rubros = $this->curso->esquemaEvaluacion()->with(['rubros.tareas', 'rubros.quizzes'])->first()?->rubros ?? collect();
        $pendientesPorTarea = $rubros->isEmpty()
            ? collect()
            : $panel->pendientesPorActividad(collect([$this->curso->id]))->pluck('pendientes', 'tarea_id');

        return view('livewire.docente.cursos.evaluaciones-curso', [
            'rubros' => $rubros,
            'avances' => $avance->deCurso($this->curso, $rubros),
            'pendientesPorTarea' => $pendientesPorTarea,
        ]);
    }
}
