<?php

namespace App\Livewire\Docente\Cursos;

use App\Models\Curso;
use App\Services\Reportes\CursosProfesor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Encabezado del detalle del curso del Profesor (mockup "CursoDetalle"):
 * datos del curso, avance, matriculados, por calificar, próximo cierre y la
 * barra de enlaces a las pantallas existentes del curso.
 *
 * Es un componente aparte para que sus consultas se hagan una sola vez al
 * cargar la página: las acciones del contenido semanal (Cursos\Show) vuelven
 * a pintar la página sin volver a calcular este resumen.
 */
class EncabezadoCurso extends Component
{
    public Curso $curso;

    public function mount(Curso $curso): void
    {
        Gate::authorize('gestionar', $curso);

        $this->curso = $curso;
    }

    public function render(CursosProfesor $servicio): View
    {
        $cursos = Curso::whereKey($this->curso->id)
            ->withCount('inscripcionesActivas')
            // Rubros (sin actividades): solo para el respaldo de "Próximo cierre" con la evaluación del Supervisor.
            ->with(['profesor', 'profesoresAdicionales', 'esquemaEvaluacion.rubros'])
            ->get();
        // Todos los cursos del profesor, solo para el color y la sección (iguales a los de la pantalla Cursos).
        $todos = Curso::dictadosPor(auth()->user())->orderBy('nombre')->orderBy('id')->get(['id', 'nombre', 'codigo', 'estado']);

        return view('livewire.docente.cursos.encabezado-curso', [
            'detalle' => $cursos->first(),
            // El encabezado no muestra avisos: no hace falta cargar las actividades de cada evaluación (las muestra EvaluacionesCurso).
            'datos' => $servicio->resumen($cursos, $todos, conAvisos: false)[$this->curso->id],
        ]);
    }
}
