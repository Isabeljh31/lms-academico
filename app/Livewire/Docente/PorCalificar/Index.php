<?php

namespace App\Livewire\Docente\PorCalificar;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Notifications\TareaCalificada;
use App\Services\Calendario\ColoresCursos;
use App\Services\Reportes\PanelProfesor;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * ERS RF-49 y RF-88: el único lugar donde el Profesor califica entregas
 * pendientes, empezando por las que más esperan. A la izquierda, las tareas
 * con pendientes (curso → semana → tarea); a la derecha, las entregas de la
 * tarea elegida. Crear o editar tareas es de Actividades; ver notas puestas,
 * del Libro de calificaciones. Lo ya calificado solo se muestra como avance y,
 * si se pide, para revisar la nota con el mismo modal.
 *
 * Pendiente = entrega en estado 'entregado' (la misma regla de la barra
 * inferior); calificada = estado 'calificado'. Una entrega grupal se muestra
 * (y se cuenta) una vez por grupo: calificarla aplica la nota a todo el grupo.
 */
#[Layout('layouts.panel-profesor')]
class Index extends Component
{
    /** Más de estos días de espera: urgente (rojo). */
    public const DIAS_URGENTE = 7;

    /** Desde estos días de espera: pide atención (ámbar). */
    public const DIAS_ATENCION = 3;

    public const ORDENES = ['espera' => 'Las que más esperan, primero', 'pendientes' => 'Más entregas por calificar, primero'];

    public const MODALIDADES = ['' => 'Todas', 'individual' => 'Individual', 'grupal' => 'Grupal'];

    /** Tipo de tarea según `tareas.practica`: la de una semana o una práctica de campo. */
    public const TIPOS = ['' => 'Todos', 'semanal' => 'Tarea de semana', 'campo' => 'Práctica de campo'];

    /** Valor del filtro de semana para las tareas cuya sección no es una semana. */
    public const SIN_SEMANA = 'sin';

    public const POR_PAGINA = [10, 20, 50];

    /** Curso elegido en "Todos los cursos" (enlace profundo ?curso=). */
    #[Url]
    public ?int $curso = null;

    /** Tarea abierta a la derecha (enlace profundo ?tarea=). */
    #[Url]
    public ?int $tarea = null;

    /** Semana del curso: '' (todas), número de semana o self::SIN_SEMANA. */
    #[Url]
    public string $semana = '';

    /** '' (todas), individual o grupal. */
    #[Url]
    public string $modalidad = '';

    /** '' (todos), semanal o campo (ver self::TIPOS). */
    #[Url]
    public string $tipo = '';

    /** Mostrar también lo ya calificado (atenuado, al final) para revisar o editar la nota. */
    #[Url]
    public bool $incluirCalificadas = false;

    public string $busqueda = '';

    public string $orden = 'espera';

    public bool $agruparPorCurso = true;

    /** Al guardar una nota, abrir la siguiente entrega pendiente de la misma tarea. */
    public bool $siguienteAlGuardar = true;

    public int $porPagina = 20;

    public int $pagina = 1;

    public bool $mostrarModalCalificar = false;

    #[Locked]
    public ?int $entregaSeleccionadaId = null;

    public string $nota = '';

    public string $feedback = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['profesor', 'supervisor']), 403);
        $this->normalizarFiltros();
    }

    public function updated(string $propiedad): void
    {
        $validos = ['orden' => array_keys(self::ORDENES), 'porPagina' => self::POR_PAGINA];

        if (isset($validos[$propiedad]) && ! in_array($this->{$propiedad}, $validos[$propiedad], true)) {
            $this->reset($propiedad);
        }

        $this->normalizarFiltros();

        if ($propiedad !== 'pagina') {
            $this->pagina = 1;
        }
    }

    /** Descarta valores de semana, modalidad o tipo que no existen (p. ej. de una URL editada). */
    protected function normalizarFiltros(): void
    {
        if ($this->semana !== '' && $this->semana !== self::SIN_SEMANA && ! ctype_digit($this->semana)) {
            $this->semana = '';
        }

        if (! array_key_exists($this->modalidad, self::MODALIDADES)) {
            $this->modalidad = '';
        }

        if (! array_key_exists($this->tipo, self::TIPOS)) {
            $this->tipo = '';
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('curso', 'busqueda', 'semana', 'modalidad', 'tipo', 'incluirCalificadas', 'pagina');
    }

    public function irAPagina(int $pagina): void
    {
        $this->pagina = max(1, $pagina);
    }

    public function abrirCalificar(int $entregaId): void
    {
        $entrega = $this->entregasCalificables()->findOrFail($entregaId);
        Gate::authorize('calificar', $entrega);

        $this->resetErrorBag();
        $this->entregaSeleccionadaId = $entrega->id;
        $this->nota = $entrega->nota !== null ? (string) (float) $entrega->nota : '';
        $this->feedback = $entrega->feedback ?? '';
        $this->mostrarModalCalificar = true;
    }

    public function guardarCalificacion(): void
    {
        $entrega = $this->entregasCalificables()->with('tarea.curso')->findOrFail($this->entregaSeleccionadaId);
        Gate::authorize('calificar', $entrega);

        $this->validate([
            'nota' => ['required', 'numeric', 'min:0', 'max:20'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ], [
            'nota.required' => 'Ingresa la nota (0 a 20).',
        ]);

        // Solo al calificar una pendiente se pasa a la siguiente: revisar una nota ya puesta no avanza.
        // Orden mostrado de las pendientes de la tarea, antes de guardar (la calificada sale de la lista).
        $avanzar = $this->siguienteAlGuardar && $entrega->estado === 'entregado';
        $ordenTarea = $avanzar ? $this->filtrar($this->filasPendientesDelProfesor($entrega->tarea_id))->pluck('entrega.id')->all() : [];

        $nota = round((float) $this->nota, 2);
        $miembros = $entrega->grupo_id
            ? Entrega::where('tarea_id', $entrega->tarea_id)->where('grupo_id', $entrega->grupo_id)->with('estudiante')->get()
            : collect([$entrega]);

        foreach ($miembros as $item) {
            $anterior = $item->nota;
            $item->update(['nota' => $nota, 'feedback' => $this->feedback ?: null, 'estado' => 'calificado']);

            if ($anterior !== null && (float) $anterior !== $nota) {
                BitacoraAuditoria::registrarCambioNota(
                    ejecutor: auth()->user(),
                    curso: $entrega->tarea->curso,
                    estudiante: $item->estudiante,
                    entidad: 'entrega_tarea',
                    entidadId: $item->id,
                    valorAnterior: $anterior,
                    valorNuevo: $nota,
                    motivo: 'Recalificación desde la bandeja Por calificar.'
                );
            }

            $item->estudiante?->notify(new TareaCalificada($item));
        }

        session()->flash('estado', $entrega->grupo_id
            ? "Calificación {$nota}/20 aplicada a todo el grupo."
            : "Entrega de {$entrega->estudiante?->name} calificada con {$nota}/20.");

        $this->mostrarModalCalificar = false;
        $this->reset(['entregaSeleccionadaId', 'nota', 'feedback']);

        $siguiente = $avanzar ? self::siguienteEnOrden($ordenTarea, $entrega->id, $miembros->pluck('id')->all()) : null;
        if ($siguiente !== null) {
            $this->abrirCalificar($siguiente);
        }
    }

    /**
     * Entregas de los cursos que dicta el usuario (titular o adicional) y que no están archivados.
     */
    protected function entregasCalificables(): Builder
    {
        return Entrega::query()->whereHas('tarea.curso', fn ($q) => $q
            ->dictadosPor(auth()->id())
            ->where('estado', '!=', 'archivado'));
    }

    /**
     * Entregas por calificar ('entregado') y, si se pide, también las calificadas, en una
     * sola consulta con todo lo que se muestra.
     *
     * @return Collection<int, Entrega>
     */
    protected function entregasDelProfesor(?int $soloTarea = null, bool $conCalificadas = true): Collection
    {
        return $this->entregasCalificables()
            ->whereIn('estado', $conCalificadas ? ['entregado', 'calificado'] : ['entregado'])
            ->when($soloTarea, fn ($q) => $q->where('tarea_id', $soloTarea))
            ->with(['estudiante:id,name', 'tarea.curso', 'tarea.modulo', 'grupo'])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Pendientes del profesor (una fila por grupo).
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function filasPendientesDelProfesor(?int $soloTarea = null): Collection
    {
        return self::filasPendientes($this->entregasDelProfesor($soloTarea, conCalificadas: false));
    }

    /**
     * Filas con la búsqueda, el curso, la semana, la modalidad y el tipo aplicados.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    protected function filtrar(Collection $filas): Collection
    {
        return self::buscar($filas, $this->busqueda)->filter(function (array $fila) {
            $tarea = $fila['entrega']->tarea;

            return ($this->curso === null || $tarea->curso_id === $this->curso)
                && self::cumpleSemana($tarea, $this->semana)
                && ($this->modalidad === '' || ($this->modalidad === 'grupal') === $tarea->esGrupal())
                && ($this->tipo === '' || self::tipoDe($tarea) === $this->tipo);
        })->values();
    }

    public function render(ColoresCursos $colores, PanelProfesor $panel)
    {
        $cursosActivos = PanelProfesor::consultaCursosActivos(auth()->id())->orderBy('nombre')->orderBy('id')
            ->get(['id', 'nombre', 'codigo', 'estado', 'tiene_practicas_campo']);

        // La misma cifra que la insignia "Por calificar" de la barra inferior (una vez por grupo); no cambia con los filtros.
        $totalPorCalificar = $panel->totalPorCalificar(auth()->user());

        $entregas = $this->entregasDelProfesor();
        [$porCalificar, $yaCalificadas] = $entregas->partition(fn (Entrega $entrega) => $entrega->estado === 'entregado');
        $pendientes = self::filasPendientes($porCalificar);
        $calificadas = self::filasCalificadas($yaCalificadas, $pendientes->pluck('clave')->all());
        $avances = self::avancesPorTarea($pendientes->concat($calificadas));

        // Todas las tareas que pasan los filtros, también las ya calificadas (cuentan en el avance de su semana).
        $todasLasTareas = self::agruparPorTarea($this->filtrar($pendientes->concat($calificadas)), $this->orden, $avances);
        $tareas = $this->incluirCalificadas ? $todasLasTareas : $todasLasTareas->where('total', '>', 0)->values();

        $seleccionada = self::tareaSeleccionada($tareas, $this->tarea, $todasLasTareas);
        if ($seleccionada && $seleccionada['tarea']->id !== $this->tarea) {
            $this->tarea = $seleccionada['tarea']->id;
            $this->pagina = 1;
        }

        $filasTarea = $seleccionada
            ? ($this->incluirCalificadas ? $seleccionada['filas'] : $seleccionada['filas']->where('calificada', false)->values())
            : collect();
        $paginas = max(1, (int) ceil($filasTarea->count() / $this->porPagina));
        $this->pagina = min($this->pagina, $paginas);

        $cursosConPendientes = $pendientes->pluck('entrega.tarea.curso_id')->unique();
        $cursosVisibles = $this->curso ? $cursosActivos->where('id', $this->curso) : $cursosActivos;
        $tareasCargadas = $entregas->pluck('tarea')->unique('id')->filter(fn (Tarea $tarea) => $cursosVisibles->contains('id', $tarea->curso_id));

        return view('livewire.docente.por-calificar.index', [
            'totalPorCalificar' => $totalPorCalificar,
            'urgentes' => $pendientes->where('urgencia', 'alta')->count(),
            'cursosActivos' => $cursosActivos,
            'cursosConPendientes' => $cursosConPendientes->count(),
            'cursosSinPendientes' => $cursosActivos->whereNotIn('id', $cursosConPendientes)->values(),
            'opcionesSemana' => $this->opcionesSemana($cursosVisibles->pluck('id'), $tareasCargadas),
            'mostrarTipo' => $this->tipo !== '' || $cursosActivos->contains(fn (Curso $curso) => $curso->tiene_practicas_campo),
            'hayCalificadas' => $calificadas->isNotEmpty(),
            'tareas' => $tareas,
            'cursosLista' => self::resumenPorCurso($tareas, $this->orden, $todasLasTareas),
            'seleccionada' => $seleccionada,
            'filasTarea' => $filasTarea->count(),
            'filasPagina' => $filasTarea->forPage($this->pagina, $this->porPagina)->values(),
            'paginas' => $paginas,
            'colores' => $colores->asignar($cursosActivos),
            'entregaSeleccionada' => $this->entregaSeleccionadaId
                ? $this->entregasCalificables()->with(['estudiante', 'tarea', 'grupo'])->find($this->entregaSeleccionadaId)
                : null,
        ])->layoutData(['porCalificar' => $totalPorCalificar])->title('Por calificar — LearnUp');
    }

    /**
     * Opciones del filtro de semana: las semanas de contenido de los cursos visibles y
     * "Sin semana" si alguna tarea con entregas no está en una semana.
     *
     * @param  Collection<int, int>  $cursoIds
     * @param  Collection<int, Tarea>  $tareasCargadas
     * @return array<string, string>
     */
    protected function opcionesSemana(Collection $cursoIds, Collection $tareasCargadas): array
    {
        $numeros = $cursoIds->isEmpty() ? collect() : Modulo::whereIn('curso_id', $cursoIds)
            ->where('tipo_seccion', Modulo::TIPO_SEMANA)->whereNotNull('semana')
            ->distinct()->orderBy('semana')->pluck('semana');

        $opciones = $numeros->mapWithKeys(fn ($numero) => [(string) $numero => self::etiquetaSemana((int) $numero)])->all();

        if ($this->semana === self::SIN_SEMANA || $tareasCargadas->contains(fn (Tarea $tarea) => self::semanaDe($tarea) === null)) {
            $opciones[self::SIN_SEMANA] = self::etiquetaSemana(null);
        }

        return $opciones;
    }

    /** Número de semana de la tarea; null si su sección no es una semana (p. ej. Prácticas de campo). */
    public static function semanaDe(Tarea $tarea): ?int
    {
        $modulo = $tarea->modulo;

        return $modulo && ! $modulo->esPracticas() && $modulo->semana !== null ? (int) $modulo->semana : null;
    }

    /** "Semana 3" o "Sin semana". */
    public static function etiquetaSemana(?int $semana): string
    {
        return $semana === null ? 'Sin semana' : "Semana {$semana}";
    }

    /** semanal o campo (práctica de campo: `tareas.practica` con número). */
    public static function tipoDe(Tarea $tarea): string
    {
        return $tarea->esPracticaCampo() ? 'campo' : 'semanal';
    }

    public static function cumpleSemana(Tarea $tarea, string $semana): bool
    {
        return match ($semana) {
            '' => true,
            self::SIN_SEMANA => self::semanaDe($tarea) === null,
            default => self::semanaDe($tarea) === (int) $semana,
        };
    }

    /** Días completos de espera desde la entrega (siempre entero; 0 si aún no pasa un día). */
    public static function diasEsperando(CarbonInterface $desde, ?CarbonInterface $ahora = null): int
    {
        return max(0, (int) floor($desde->diffInDays($ahora ?? now(), false)));
    }

    /** «hoy», «hace 1 día» o «hace N días». */
    public static function textoEspera(int $dias): string
    {
        return match (true) {
            $dias <= 0 => 'hoy',
            $dias === 1 => 'hace 1 día',
            default => "hace {$dias} días",
        };
    }

    /** alta: más de 7 días; media: de 3 a 7 días; baja: menos de 3. */
    public static function urgencia(int $dias): string
    {
        return match (true) {
            $dias > self::DIAS_URGENTE => 'alta',
            $dias >= self::DIAS_ATENCION => 'media',
            default => 'baja',
        };
    }

    /** Clave de una fila: una por grupo en cada tarea, o una por entrega individual. */
    protected static function claveDe(Entrega $entrega): string
    {
        return $entrega->grupo_id ? "g{$entrega->tarea_id}-{$entrega->grupo_id}" : "e{$entrega->id}";
    }

    /**
     * Una fila por entrega individual y una por grupo (la entrega más antigua del grupo),
     * de la más antigua a la más reciente.
     *
     * @param  Collection<int, Entrega>  $entregas  pendientes, con estudiante, tarea.curso y grupo cargados
     * @return Collection<int, array{clave: string, entrega: Entrega, miembros: list<string>, grupo: ?string, dias: int, urgencia: string, calificada: bool, nota: ?float}>
     */
    public static function filasPendientes(Collection $entregas, ?CarbonInterface $ahora = null): Collection
    {
        return $entregas
            ->sortBy([fn (Entrega $a, Entrega $b) => [$a->updated_at, $a->id] <=> [$b->updated_at, $b->id]])
            ->groupBy(fn (Entrega $entrega) => self::claveDe($entrega))
            ->map(function (Collection $delMismo, string $clave) use ($ahora) {
                $primera = $delMismo->first();
                $dias = self::diasEsperando($primera->updated_at, $ahora);

                return [
                    'clave' => $clave,
                    'entrega' => $primera,
                    'miembros' => $delMismo->map(fn (Entrega $entrega) => (string) $entrega->estudiante?->name)->filter()->values()->all(),
                    'grupo' => $primera->grupo?->nombre,
                    'dias' => $dias,
                    'urgencia' => self::urgencia($dias),
                    'calificada' => false,
                    'nota' => null,
                ];
            })
            ->values();
    }

    /**
     * Filas ya calificadas (una por grupo), sin los grupos que aún tienen alguna entrega
     * pendiente: esos cuentan como pendientes.
     *
     * @param  Collection<int, Entrega>  $entregas  calificadas, con estudiante, tarea.curso y grupo cargados
     * @param  list<string>  $clavesPendientes
     * @return Collection<int, array<string, mixed>>
     */
    public static function filasCalificadas(Collection $entregas, array $clavesPendientes = []): Collection
    {
        return $entregas->toBase()
            ->sortBy([fn (Entrega $a, Entrega $b) => [$a->updated_at, $a->id] <=> [$b->updated_at, $b->id]])
            ->groupBy(fn (Entrega $entrega) => self::claveDe($entrega))
            ->except($clavesPendientes)
            ->map(fn (Collection $delMismo, string $clave) => [
                'clave' => $clave,
                'entrega' => $delMismo->first(),
                'miembros' => $delMismo->map(fn (Entrega $entrega) => (string) $entrega->estudiante?->name)->filter()->values()->all(),
                'grupo' => $delMismo->first()->grupo?->nombre,
                'dias' => 0,
                'urgencia' => 'baja',
                'calificada' => true,
                'nota' => $delMismo->first()->nota !== null ? (float) $delMismo->first()->nota : null,
            ])
            ->values();
    }

    /**
     * Avance de cada tarea sobre todas sus filas (sin filtros): calificadas de
     * calificadas + pendientes, en grupos si es grupal.
     *
     * @param  Collection<int, array<string, mixed>>  $filas  pendientes y calificadas
     * @return array<int, array{calificadas: int, total: int}>
     */
    public static function avancesPorTarea(Collection $filas): array
    {
        return $filas
            ->groupBy(fn (array $fila) => $fila['entrega']->tarea_id)
            ->map(fn (Collection $deLaTarea) => [
                'calificadas' => $deLaTarea->where('calificada', true)->count(),
                'total' => $deLaTarea->count(),
            ])
            ->all();
    }

    /**
     * Filas cuyo estudiante (o algún integrante del grupo) o tarea contienen el texto, sin
     * distinguir mayúsculas.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    public static function buscar(Collection $filas, string $texto): Collection
    {
        $texto = mb_strtolower(trim($texto));

        if ($texto === '') {
            return $filas;
        }

        return $filas->filter(fn (array $fila) => str_contains(mb_strtolower($fila['entrega']->tarea->titulo), $texto)
            || collect($fila['miembros'])->contains(fn (string $nombre) => str_contains(mb_strtolower($nombre), $texto))
        )->values();
    }

    /**
     * Filas agrupadas por tarea: primero las pendientes (de la que más espera a la más
     * reciente) y después las calificadas. Orden «espera»: la tarea con la pendiente más
     * antigua primero; «pendientes»: la que tiene más. Las ya calificadas van al final.
     *
     * @param  Collection<int, array<string, mixed>>  $filas  de filasPendientes() y filasCalificadas()
     * @param  array<int, array{calificadas: int, total: int}>  $avances  de avancesPorTarea()
     * @return Collection<int, array{tarea: Tarea, grupal: bool, semana: ?int, tipo: string, filas: Collection<int, array<string, mixed>>, primera: array<string, mixed>, total: int, urgentes: int, completa: bool, avance: array{calificadas: int, total: int}}>
     */
    public static function agruparPorTarea(Collection $filas, string $orden = 'espera', array $avances = []): Collection
    {
        $tareas = $filas
            ->groupBy(fn (array $fila) => $fila['entrega']->tarea_id)
            ->map(function (Collection $deLaTarea, int $tareaId) use ($avances) {
                $pendientes = $deLaTarea->where('calificada', false)->values();
                $calificadas = $deLaTarea->where('calificada', true)->values();
                $tarea = $deLaTarea->first()['entrega']->tarea;

                return [
                    'tarea' => $tarea,
                    'grupal' => $tarea->esGrupal(),
                    'semana' => self::semanaDe($tarea),
                    'tipo' => self::tipoDe($tarea),
                    'filas' => $pendientes->concat($calificadas)->values(),
                    'primera' => $pendientes->first() ?? $calificadas->first(),
                    'total' => $pendientes->count(),
                    'urgentes' => $pendientes->where('urgencia', 'alta')->count(),
                    'completa' => $pendientes->isEmpty(),
                    'avance' => $avances[$tareaId] ?? ['calificadas' => $calificadas->count(), 'total' => $deLaTarea->count()],
                ];
            })
            ->values();

        $porEspera = fn (array $tarea) => [$tarea['completa'], $tarea['primera']['entrega']->updated_at, $tarea['primera']['entrega']->id];

        return $tareas->sort(fn (array $a, array $b) => $orden === 'pendientes'
            ? [$a['completa'], $b['total'], ...array_slice($porEspera($a), 1)] <=> [$b['completa'], $a['total'], ...array_slice($porEspera($b), 1)]
            : $porEspera($a) <=> $porEspera($b)
        )->values();
    }

    /**
     * Tarea pedida si está entre las visibles (o es una ya calificada del todo que sigue
     * pasando los filtros); si no, la de la entrega pendiente que más espera.
     *
     * @param  Collection<int, array<string, mixed>>  $tareas  de agruparPorTarea(), las que se listan
     * @param  Collection<int, array<string, mixed>>|null  $todas  también las ya calificadas del todo
     * @return array<string, mixed>|null
     */
    public static function tareaSeleccionada(Collection $tareas, ?int $tareaId, ?Collection $todas = null): ?array
    {
        return $tareas->first(fn (array $tarea) => $tarea['tarea']->id === $tareaId)
            ?? ($todas ?? collect())->first(fn (array $tarea) => $tarea['tarea']->id === $tareaId && $tarea['completa'])
            ?? $tareas->sortBy(fn (array $tarea) => [$tarea['completa'], $tarea['primera']['entrega']->updated_at, $tarea['primera']['entrega']->id])->first();
    }

    /**
     * Cursos de la lista izquierda, en el orden de sus tareas (la primera tarea de cada curso
     * manda), con sus semanas en orden ascendente ("Sin semana" al final). El avance de cada
     * semana cuenta todas sus tareas, también las ya calificadas que no se listan.
     *
     * @param  Collection<int, array<string, mixed>>  $tareas  de agruparPorTarea(), ya ordenadas (las que se listan)
     * @param  Collection<int, array<string, mixed>>|null  $todas  todas las que pasan los filtros
     * @return Collection<int, array{curso: Curso, iniciales: string, total: int, primera: array<string, mixed>, tareas: Collection<int, array<string, mixed>>, semanas: Collection<int, array<string, mixed>>}>
     */
    public static function resumenPorCurso(Collection $tareas, string $orden = 'espera', ?Collection $todas = null): Collection
    {
        $todas ??= $tareas;

        $cursos = $tareas
            ->groupBy(fn (array $tarea) => $tarea['tarea']->curso_id)
            ->map(function (Collection $delCurso, int $cursoId) use ($todas) {
                $primera = $delCurso->sortBy(fn (array $tarea) => [$tarea['completa'], $tarea['primera']['entrega']->updated_at, $tarea['primera']['entrega']->id])->first()['primera'];
                $todasDelCurso = $todas->filter(fn (array $tarea) => $tarea['tarea']->curso_id === $cursoId);

                $semanas = $delCurso
                    ->groupBy(fn (array $tarea) => $tarea['semana'] ?? self::SIN_SEMANA)
                    ->map(function (Collection $deLaSemana) use ($todasDelCurso) {
                        $numero = $deLaSemana->first()['semana'];
                        $todasDeLaSemana = $todasDelCurso->filter(fn (array $tarea) => $tarea['semana'] === $numero);
                        $calificadas = $todasDeLaSemana->sum(fn (array $tarea) => $tarea['avance']['calificadas']);
                        $total = $todasDeLaSemana->sum(fn (array $tarea) => $tarea['avance']['total']);

                        return [
                            'clave' => $numero ?? self::SIN_SEMANA,
                            'numero' => $numero,
                            'etiqueta' => self::etiquetaSemana($numero),
                            'calificadas' => $calificadas,
                            'total' => $total,
                            'completa' => $total > 0 && $calificadas === $total,
                            'tareas' => $deLaSemana->values(),
                        ];
                    })
                    ->sortBy(fn (array $semana) => [$semana['numero'] === null, $semana['numero'] ?? 0])
                    ->values();

                return [
                    'curso' => $delCurso->first()['tarea']->curso,
                    'iniciales' => PanelProfesor::iniciales($delCurso->first()['tarea']->curso->nombre),
                    'total' => $delCurso->sum('total'),
                    'primera' => $primera,
                    'tareas' => $delCurso->values(),
                    'semanas' => $semanas,
                ];
            })
            ->values();

        return $orden === 'pendientes' ? $cursos->sortByDesc('total')->values() : $cursos;
    }

    /**
     * La entrega que sigue a la calificada en el orden mostrado (sin los integrantes de su
     * grupo); si era la última, la primera que quede. Null si no queda ninguna.
     *
     * @param  list<int>  $orden  ids de las filas pendientes de la tarea, en el orden mostrado
     * @param  list<int>  $calificadas  ids de las entregas que se acaban de calificar
     */
    public static function siguienteEnOrden(array $orden, int $actual, array $calificadas): ?int
    {
        $posicion = array_search($actual, $orden, true);
        $restantes = array_values(array_diff($orden, $calificadas, [$actual]));

        if ($restantes === []) {
            return null;
        }

        if ($posicion === false) {
            return $restantes[0];
        }

        $despues = array_values(array_filter($restantes, fn (int $id) => array_search($id, $orden, true) > $posicion));

        return $despues[0] ?? $restantes[0];
    }
}
