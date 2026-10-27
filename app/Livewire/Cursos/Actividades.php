<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\Models\Quiz;
use App\Models\Tarea;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Apartado "Actividades" del Profesor del curso: en una sola pantalla, con
 * dos pestañas, la lista unificada de tareas y quizzes (filtro por tipo y
 * búsqueda) y el Banco de preguntas (componente Quizzes\BancoPreguntas).
 *
 * Crear y editar reutilizan los formularios existentes (Tareas\Formulario y
 * Quizzes\Formulario) con ?desde=actividades para volver aquí. Las pantallas
 * de Tareas y Quizzes siguen igual para el Estudiante y el Supervisor.
 *
 * ERS RF-39 a RF-47 (tareas, quizzes y banco); agrupación: extensión de UX.
 */
#[Layout('layouts.app')]
class Actividades extends Component
{
    use WithPagination;

    public const POR_PAGINA = 10;

    /** @var array<string, string> */
    public const PESTANAS = ['actividades' => 'Actividades', 'banco' => 'Banco de preguntas'];

    /** @var array<string, string> */
    public const FILTROS = ['todas' => 'Todas', 'tareas' => 'Tareas', 'quizzes' => 'Quizzes'];

    public Curso $curso;

    #[Url]
    public string $pestana = 'actividades';

    #[Url]
    public string $tipo = 'todas';

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    public bool $mostrarConfirmacion = false;

    #[Locked]
    public ?string $eliminarTipo = null;

    #[Locked]
    public ?int $eliminarId = null;

    #[Locked]
    public string $eliminarTitulo = '';

    public function mount(Curso $curso): void
    {
        Gate::authorize('gestionar', $curso);
        $this->curso = $curso;
        $this->normalizarFiltros();
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['tipo', 'busqueda'], true)) {
            $this->normalizarFiltros();
            $this->resetPage('pagina');
        }

        if ($propiedad === 'pestana') {
            $this->normalizarFiltros();
        }
    }

    public function filtrar(string $tipo): void
    {
        $this->tipo = $tipo;
        $this->normalizarFiltros();
        $this->resetPage('pagina');
    }

    public function confirmarEliminar(string $tipo, int $id): void
    {
        $actividad = $this->actividad($tipo, $id);
        Gate::authorize('eliminar', $actividad);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado.');

        $this->eliminarTipo = $tipo;
        $this->eliminarId = $actividad->id;
        $this->eliminarTitulo = $tipo === 'tarea' ? $actividad->titulo : $actividad->tema;
        $this->mostrarConfirmacion = true;
    }

    public function cerrarConfirmacion(): void
    {
        $this->reset(['mostrarConfirmacion', 'eliminarTipo', 'eliminarId', 'eliminarTitulo']);
    }

    public function eliminar(): void
    {
        abort_if($this->eliminarTipo === null || $this->eliminarId === null, 404);

        $actividad = $this->actividad($this->eliminarTipo, $this->eliminarId);
        Gate::authorize('eliminar', $actividad);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado.');

        $etiqueta = $this->eliminarTipo === 'tarea' ? "Tarea \"{$actividad->titulo}\"" : "Quiz \"{$actividad->tema}\"";
        $actividad->delete();
        $this->cerrarConfirmacion();
        session()->flash('estado', "{$etiqueta} eliminado.");
    }

    /** Tarea o quiz de ESTE curso (protección IDOR). */
    protected function actividad(string $tipo, int $id): Tarea|Quiz
    {
        return match ($tipo) {
            'tarea' => $this->curso->tareas()->find($id) ?? abort(404),
            'quiz' => $this->curso->quizzes()->find($id) ?? abort(404),
            default => abort(404),
        };
    }

    protected function normalizarFiltros(): void
    {
        $this->pestana = array_key_exists($this->pestana, self::PESTANAS) ? $this->pestana : 'actividades';
        $this->tipo = array_key_exists($this->tipo, self::FILTROS) ? $this->tipo : 'todas';
    }

    /**
     * Tareas y quizzes del curso como filas uniformes, ordenadas por fecha
     * límite (las sin fecha al final). Dos consultas con eager loading y
     * conteos, sin importar cuántas actividades haya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function filas(): Collection
    {
        $busqueda = trim($this->busqueda);

        $tareas = $this->tipo === 'quizzes' ? collect() : $this->curso->tareas()
            ->with('modulo')
            ->withCount(['entregas', 'entregas as por_calificar_count' => fn ($q) => $q->where('estado', '!=', 'calificado')])
            ->when($busqueda !== '', fn ($q) => $q->where('titulo', 'like', "%{$busqueda}%"))
            ->get()
            ->map(fn (Tarea $tarea) => [
                'tipo' => 'tarea',
                'id' => $tarea->id,
                'titulo' => $tarea->titulo,
                'semana' => $tarea->modulo?->nombreCompleto(),
                'estado' => $tarea->etiquetaEstadoPublicacion(),
                'fecha' => $tarea->fecha_entrega,
                'dato' => $tarea->entregas_count === 0
                    ? 'Sin entregas'
                    : "{$tarea->por_calificar_count} por calificar de {$tarea->entregas_count} ".($tarea->entregas_count === 1 ? 'entrega' : 'entregas'),
                'destacarDato' => $tarea->por_calificar_count > 0,
                'puedeEliminarse' => $tarea->entregas_count === 0,
                'motivoNoEliminar' => 'Tiene entregas: no se puede eliminar (puedes ocultarla desde Tareas).',
                'urlVer' => route('cursos.tareas.show', [$this->curso, $tarea]),
                'urlEditar' => route('cursos.tareas.editar', [$this->curso, $tarea, 'desde' => 'actividades']),
            ]);

        $quizzes = $this->tipo === 'tareas' ? collect() : $this->curso->quizzes()
            ->with('modulo')
            ->withCount('resultados')
            ->when($busqueda !== '', fn ($q) => $q->where('tema', 'like', "%{$busqueda}%"))
            ->get()
            ->map(fn (Quiz $quiz) => [
                'tipo' => 'quiz',
                'id' => $quiz->id,
                'titulo' => $quiz->tema,
                'semana' => $quiz->modulo?->nombreCompleto(),
                'estado' => $quiz->etiquetaEstadoPublicacion(),
                'fecha' => $quiz->fecha_limite,
                'dato' => count($quiz->preguntas ?? []).' preguntas · '.$quiz->resultados_count.' '.($quiz->resultados_count === 1 ? 'intento' : 'intentos'),
                'destacarDato' => false,
                'puedeEliminarse' => $quiz->resultados_count === 0,
                'motivoNoEliminar' => 'Tiene intentos de estudiantes: no se puede eliminar (puedes ocultarlo desde Quizzes).',
                'urlVer' => route('cursos.quizzes.show', [$this->curso, $quiz]),
                'urlEditar' => route('cursos.quizzes.editar', [$this->curso, $quiz, 'desde' => 'actividades']),
            ]);

        return $tareas->concat($quizzes)
            ->sortBy([
                fn ($a, $b) => ($a['fecha'] === null) <=> ($b['fecha'] === null),
                fn ($a, $b) => ($a['fecha']?->getTimestamp() ?? 0) <=> ($b['fecha']?->getTimestamp() ?? 0),
                fn ($a, $b) => strcasecmp($a['titulo'], $b['titulo']),
            ])
            ->values();
    }

    public function render()
    {
        $filas = $this->filas();
        $pagina = $this->getPage('pagina');

        $actividades = new LengthAwarePaginator(
            $filas->forPage($pagina, self::POR_PAGINA)->values(),
            $filas->count(),
            self::POR_PAGINA,
            $pagina,
            ['pageName' => 'pagina'],
        );

        return view('livewire.cursos.actividades', [
            'actividades' => $actividades,
            'totales' => [
                'tareas' => $this->curso->tareas()->count(),
                'quizzes' => $this->curso->quizzes()->count(),
            ],
            'editable' => ! $this->curso->estaFinalizado(),
        ])->title("Actividades — {$this->curso->nombre}");
    }
}
