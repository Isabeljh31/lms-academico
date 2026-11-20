<?php

namespace App\Livewire\Docente;

use App\Services\Calendario\ColoresCursos;
use App\Services\Calendario\SeccionesProfesor;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Reportes\PanelProfesor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Panel de inicio del Profesor (ERS RF-88), con su propio layout de barra
 * inferior. Es un resumen con tope: su largo no crece con la cantidad de
 * cursos; lo que no entra se enlaza a la pantalla que ya existe.
 */
class Panel extends Component
{
    /** Filas de "Para hacer ahora". */
    public const MAX_PENDIENTES = 5;

    /** Filas de "Lo que se viene". */
    public const MAX_PROXIMAS = 5;

    /** Comunicados visibles (y tope al desplegarlos todos dentro de la columna). */
    public const MAX_COMUNICADOS = 3;

    public const MAX_COMUNICADOS_DESPLEGADOS = 20;

    /** Con más cursos que esto, los chips de curso pasan a un desplegable. */
    public const MAX_CHIPS_CURSO = 4;

    /** Curso elegido en "Para hacer ahora" (0 = todos). */
    public int $cursoPendientes = 0;

    /** Tipo elegido en "Lo que se viene": todo, cierre, contenido o evaluacion. */
    public string $tipoProximo = 'todo';

    /** Curso elegido en "Lo que se viene" (0 = todos). */
    public int $cursoProximo = 0;

    /** Filtro de comunicados: todos, urgentes o institucionales. */
    public string $filtroComunicados = 'todos';

    public bool $todosLosComunicados = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('profesor'), 403);
    }

    public function updatedTipoProximo(string $valor): void
    {
        if (! in_array($valor, ['todo', 'cierre', 'contenido', 'evaluacion'], true)) {
            $this->tipoProximo = 'todo';
        }
    }

    public function updatedFiltroComunicados(string $valor): void
    {
        if (! in_array($valor, ['todos', 'urgentes', 'institucionales'], true)) {
            $this->filtroComunicados = 'todos';
        }
    }

    public function render(PanelProfesor $panel, CalculadoraNotas $calculadora, ColoresCursos $colores): View
    {
        $profesor = auth()->user();
        $cursos = $panel->cursos($profesor);
        $cursoIds = $cursos->modelKeys();
        $secciones = new SeccionesProfesor($cursos);
        $colorPorCurso = $colores->asignar($cursos);

        // Un curso elegido que ya no es del profesor se ignora (vuelve a "todos").
        $this->cursoPendientes = in_array($this->cursoPendientes, $cursoIds, true) ? $this->cursoPendientes : 0;
        $this->cursoProximo = in_array($this->cursoProximo, $cursoIds, true) ? $this->cursoProximo : 0;

        $actividades = $panel->pendientesPorActividad(collect($cursoIds));
        $pendientesPorCurso = $actividades->groupBy('curso_id')->map(fn (Collection $filas) => $filas->sum('pendientes'));
        $quizzes = $panel->quizzesVisibles(collect($cursoIds));
        $semanas = $panel->semanasConLecciones(collect($cursoIds));
        $promedios = $panel->promediosPorCurso($cursos);

        return view('livewire.docente.panel', [
            'profesor' => $profesor,
            'cursos' => $cursos,
            'secciones' => $secciones,
            'colores' => $colorPorCurso,
            'notaMinima' => $calculadora->notaMinima(),
            'indicadores' => [
                'cursos' => $cursos->count(),
                'estudiantes' => (int) $cursos->sum('inscripciones_activas_count'),
                'porCalificar' => (int) $actividades->sum('pendientes'),
                'quizzesActivos' => $quizzes->filter->estaAbierto()->count(),
                'promedio' => $panel->promedioGeneral($promedios),
            ],
            'pendientes' => $this->pendientes($actividades, $pendientesPorCurso),
            'proximas' => $this->proximas($panel->proximasFechas($cursos, $quizzes, $semanas)),
            'comunicados' => $panel->comunicados($this->filtroComunicados, $this->todosLosComunicados ? self::MAX_COMUNICADOS_DESPLEGADOS : self::MAX_COMUNICADOS),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $actividades
     * @param  Collection<int, int>  $pendientesPorCurso
     * @return array<string, mixed>
     */
    protected function pendientes(Collection $actividades, Collection $pendientesPorCurso): array
    {
        $filtradas = $this->cursoPendientes
            ? $actividades->where('curso_id', $this->cursoPendientes)->values()
            : $actividades;

        return [
            'total' => (int) $actividades->sum('pendientes'),
            'actividades' => $actividades->count(),
            'porCurso' => $pendientesPorCurso->sortDesc(),
            'filas' => $filtradas->take(self::MAX_PENDIENTES),
            'restantes' => max(0, $filtradas->count() - self::MAX_PENDIENTES),
            'masAntigua' => $actividades->first(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $todas
     * @return array<string, mixed>
     */
    protected function proximas(Collection $todas): array
    {
        $delCurso = $this->cursoProximo ? $todas->where('curso_id', $this->cursoProximo)->values() : $todas;
        $filtradas = $this->tipoProximo === 'todo' ? $delCurso : $delCurso->where('tipo', $this->tipoProximo)->values();

        return [
            'conteos' => [
                'todo' => $delCurso->count(),
                'cierre' => $delCurso->where('tipo', 'cierre')->count(),
                'contenido' => $delCurso->where('tipo', 'contenido')->count(),
                'evaluacion' => $delCurso->where('tipo', 'evaluacion')->count(),
            ],
            'cursosConFechas' => $todas->pluck('curso_id')->unique()->values(),
            'filas' => $filtradas->take(self::MAX_PROXIMAS),
            'restantes' => max(0, $filtradas->count() - self::MAX_PROXIMAS),
        ];
    }
}
