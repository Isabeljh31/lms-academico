<?php

namespace App\Livewire\Profesor\Cursos;

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
    /** Pestañas del curso que puede marcar como activa una subpantalla. */
    public const PESTANAS = ['contenido', 'actividades', 'matricula', 'grupos', 'debates', 'asistencia', 'libro', 'mensajeria'];

    public Curso $curso;

    /** Variante compacta (banda del curso + pestañas, sin indicadores) para las subpantallas del curso. */
    public bool $compacto = false;

    public string $activa = 'contenido';

    public function mount(Curso $curso, bool $compacto = false, string $activa = 'contenido'): void
    {
        Gate::authorize('gestionar', $curso);

        $this->curso = $curso;
        $this->compacto = $compacto;
        $this->activa = in_array($activa, self::PESTANAS, true) ? $activa : 'contenido';
    }

    public function render(CursosProfesor $servicio): View
    {
        if ($this->compacto) {
            $detalle = Curso::whereKey($this->curso->id)->withCount(['inscripcionesActivas', 'tareas', 'quizzes', 'grupos', 'debates'])->first();
            $todos = Curso::dictadosPor(auth()->user())->orderBy('nombre')->orderBy('id')->get(['id', 'nombre', 'codigo', 'estado']);

            return view('livewire.profesor.cursos.encabezado-curso', [
                'detalle' => $detalle,
                'datos' => $servicio->identidad($detalle, $todos),
            ]);
        }

        $cursos = Curso::whereKey($this->curso->id)
            // Contadores de las pestañas en la misma consulta (subconsultas agregadas).
            ->withCount(['inscripcionesActivas', 'tareas', 'quizzes', 'grupos', 'debates'])
            // Rubros (sin actividades): solo para el respaldo de "Próximo cierre" con la evaluación del Supervisor.
            ->with(['profesor', 'profesoresAdicionales', 'esquemaEvaluacion.rubros'])
            ->get();
        // Todos los cursos del profesor, solo para el color y la sección (iguales a los de la pantalla Cursos).
        $todos = Curso::dictadosPor(auth()->user())->orderBy('nombre')->orderBy('id')->get(['id', 'nombre', 'codigo', 'estado']);

        return view('livewire.profesor.cursos.encabezado-curso', [
            'detalle' => $cursos->first(),
            // El encabezado no muestra avisos: no hace falta cargar las actividades de cada evaluación (las muestra EvaluacionesCurso).
            'datos' => $servicio->resumen($cursos, $todos, conAvisos: false)[$this->curso->id],
        ]);
    }
}
