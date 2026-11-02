<?php

namespace App\Livewire\Profesor;

use App\Enums\ModoCalculo;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Quiz;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
    public const FILTROS = ['todas' => 'Todas', 'tareas' => 'Tareas', 'quizzes' => 'Quizzes', 'sin_evaluacion' => 'Sin evaluación'];

    public Curso $curso;

    #[Url]
    public string $pestana = 'actividades';

    #[Url]
    public string $tipo = 'todas';

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    public bool $mostrarConfirmacion = false;

    public bool $mostrarCalculo = false;

    #[Locked]
    public ?int $rubroCalculoId = null;

    public string $modoCalculo = 'promedio';

    /** @var list<int> */
    public array $tareasCalculo = [];

    /** @var list<int> */
    public array $quizzesCalculo = [];

    /**
     * Peso (%) de cada actividad elegida en modo Promedio, con clave
     * «tarea-{id}» o «quiz-{id}».
     *
     * @var array<string, string>
     */
    public array $pesosCalculo = [];

    /** Actividad elegida en modo Examen («tarea-{id}» o «quiz-{id}»). */
    public ?string $examenCalculo = null;

    /**
     * Abre el panel de cálculo de una evaluación. Con $actividad («tarea-5»)
     * la deja preseleccionada: viene de la insignia «Sin evaluación» de la lista.
     */
    public function configurarCalculo(int $rubroId, ?string $actividad = null): void
    {
        Gate::authorize('calificar', $this->curso);
        abort_if($this->curso->estaFinalizado(), 403);
        $rubro = $this->curso->esquemaEvaluacion?->rubros()->with(['tareas', 'quizzes'])->find($rubroId) ?? abort(404);
        $this->resetErrorBag();
        $this->rubroCalculoId = $rubro->id;
        $this->modoCalculo = ($rubro->modo_calculo ?? ModoCalculo::Promedio)->value;
        $this->tareasCalculo = $rubro->tareas->modelKeys();
        $this->quizzesCalculo = $rubro->quizzes->modelKeys();
        $this->pesosCalculo = $rubro->actividadesVinculadas()
            ->mapWithKeys(fn (array $vinculada) => [$vinculada['tipo'].'-'.$vinculada['id'] => $this->formatearPeso($vinculada['peso'])])->all();
        $this->examenCalculo = $this->modoCalculo === ModoCalculo::Examen->value ? array_key_first($this->pesosCalculo) : null;

        if ($actividad !== null && preg_match('/^(tarea|quiz)-(\d+)$/', $actividad, $partes)) {
            if ($this->modoCalculo === ModoCalculo::Examen->value) {
                $this->examenCalculo = $actividad;
            } else {
                $this->modoCalculo = ModoCalculo::Promedio->value;
                $propiedad = $partes[1] === 'tarea' ? 'tareasCalculo' : 'quizzesCalculo';
                $this->{$propiedad} = array_values(array_unique([...$this->{$propiedad}, (int) $partes[2]]));
                $this->ajustarPesos();
            }
        }

        $this->mostrarCalculo = true;
    }

    public function updatedTareasCalculo(): void
    {
        $this->ajustarPesos();
    }

    public function updatedQuizzesCalculo(): void
    {
        $this->ajustarPesos();
    }

    public function repartirPesos(): void
    {
        $this->pesosCalculo = $this->repartoIgual($this->clavesSeleccionadas());
    }

    public function guardarCalculo(): void
    {
        Gate::authorize('calificar', $this->curso);
        abort_if($this->curso->estaFinalizado(), 403);
        $rubro = $this->curso->esquemaEvaluacion?->rubros()->find($this->rubroCalculoId) ?? abort(404);
        $this->validate([
            'modoCalculo' => ['required', Rule::enum(ModoCalculo::class)],
            'tareasCalculo' => ['array', 'max:200'],
            'tareasCalculo.*' => ['integer', 'distinct', Rule::exists('tareas', 'id')->where('curso_id', $this->curso->id)],
            'quizzesCalculo' => ['array', 'max:200'],
            'quizzesCalculo.*' => ['integer', 'distinct', Rule::exists('quizzes', 'id')->where('curso_id', $this->curso->id)],
            'examenCalculo' => ['nullable', 'string', 'regex:/^(tarea|quiz)-\d+$/'],
        ]);

        $vinculos = match (ModoCalculo::from($this->modoCalculo)) {
            ModoCalculo::Manual => [],
            ModoCalculo::Promedio => $this->vinculosDePromedio(),
            ModoCalculo::Examen => $this->vinculosDeExamen(),
        };
        if ($vinculos === null || ! $this->actividadesLibresPara($rubro, $vinculos)) {
            return;
        }

        DB::transaction(function () use ($rubro, $vinculos) {
            $anterior = ['modo' => $rubro->modo_calculo?->value, 'actividades' => $rubro->load(['tareas', 'quizzes'])->actividadesVinculadas()->all()];
            $rubro->update(['modo_calculo' => $this->modoCalculo]);
            $rubro->tareas()->sync(collect($vinculos)->where('tipo', 'tarea')->mapWithKeys(fn (array $vinculo) => [$vinculo['id'] => ['peso' => $vinculo['peso']]])->all());
            $rubro->quizzes()->sync(collect($vinculos)->where('tipo', 'quiz')->mapWithKeys(fn (array $vinculo) => [$vinculo['id'] => ['peso' => $vinculo['peso']]])->all());
            BitacoraAuditoria::registrarAccionAdmin(
                ejecutor: auth()->user(), tipoEvento: 'modificacion_esquema',
                descripcion: 'Cálculo de notas configurado para '.$rubro->nombre,
                curso: $this->curso, valorAnterior: $anterior,
                valorNuevo: ['modo' => $this->modoCalculo, 'actividades' => $vinculos],
            );
        });
        $this->mostrarCalculo = false;
        session()->flash('estado', "Cálculo de {$rubro->nombre} guardado.");
    }

    /**
     * Actividades elegidas en modo Promedio con su peso; null (con errores)
     * si no hay ninguna, algún peso no es válido o no suman 100 %.
     *
     * @return list<array{tipo: string, id: int, peso: float}>|null
     */
    protected function vinculosDePromedio(): ?array
    {
        $claves = $this->clavesSeleccionadas();
        if ($claves === []) {
            $this->addError('actividadesCalculo', 'Elige al menos una actividad.');

            return null;
        }

        $vinculos = [];
        foreach ($claves as $clave) {
            $peso = $this->pesoNumerico($this->pesosCalculo[$clave] ?? '');
            if ($peso === null || $peso < 1 || $peso > 100) {
                $this->addError("pesosCalculo.$clave", 'Usa un valor entre 1 y 100.');

                continue;
            }
            [$tipo, $id] = explode('-', $clave);
            $vinculos[] = ['tipo' => $tipo, 'id' => (int) $id, 'peso' => $peso];
        }
        if (count($vinculos) !== count($claves)) {
            return null;
        }

        $faltante = round(100 - array_sum(array_column($vinculos, 'peso')), 2);
        if ($faltante != 0) {
            $this->addError('pesosCalculo', $this->mensajeTotal($faltante));

            return null;
        }

        return $vinculos;
    }

    /** @return list<array{tipo: string, id: int, peso: float}>|null */
    protected function vinculosDeExamen(): ?array
    {
        if ($this->examenCalculo === null) {
            $this->addError('examenCalculo', 'Elige el examen de esta evaluación.');

            return null;
        }

        [$tipo, $id] = explode('-', $this->examenCalculo);
        $actividades = $tipo === 'tarea' ? $this->curso->tareas() : $this->curso->quizzes();
        if ($actividades->whereKey((int) $id)->doesntExist()) {
            $this->addError('examenCalculo', 'Elige una actividad de este curso.');

            return null;
        }

        return [['tipo' => $tipo, 'id' => (int) $id, 'peso' => 100.0]];
    }

    /**
     * Una actividad cuenta para una sola evaluación.
     *
     * @param  list<array{tipo: string, id: int, peso: float}>  $vinculos
     */
    protected function actividadesLibresPara(RubroEvaluacion $rubro, array $vinculos): bool
    {
        $vinculos = collect($vinculos);
        $enOtraEvaluacion = fn ($consulta) => $consulta->whereHas('rubrosEvaluacion', fn ($q) => $q->whereKeyNot($rubro->id))
            ->with(['rubrosEvaluacion' => fn ($q) => $q->whereKeyNot($rubro->id)]);
        $ocupadas = $enOtraEvaluacion($this->curso->tareas()->whereKey($vinculos->where('tipo', 'tarea')->pluck('id')))->get()
            ->map(fn (Tarea $tarea) => "«{$tarea->titulo}» ya cuenta para {$tarea->rubrosEvaluacion->first()->nombre}.")
            ->concat($enOtraEvaluacion($this->curso->quizzes()->whereKey($vinculos->where('tipo', 'quiz')->pluck('id')))->get()
                ->map(fn (Quiz $quiz) => "«{$quiz->tema}» ya cuenta para {$quiz->rubrosEvaluacion->first()->nombre}."));

        $ocupadas->each(fn (string $mensaje) => $this->addError('actividadesCalculo', $mensaje));

        return $ocupadas->isEmpty();
    }

    /** @return list<string> */
    protected function clavesSeleccionadas(): array
    {
        return [
            ...array_map(fn ($id) => 'tarea-'.(int) $id, $this->tareasCalculo),
            ...array_map(fn ($id) => 'quiz-'.(int) $id, $this->quizzesCalculo),
        ];
    }

    /**
     * Mantiene los pesos al marcar o desmarcar actividades: si seguían
     * repartidos en partes iguales se vuelven a repartir; si el profesor los
     * ajustó, la actividad nueva recibe lo que falta para 100 %.
     */
    protected function ajustarPesos(): void
    {
        $claves = $this->clavesSeleccionadas();
        $anteriores = $this->pesosCalculo;
        $eranIguales = $anteriores === [] || $anteriores === $this->repartoIgual(array_keys($anteriores));

        if ($eranIguales) {
            $this->pesosCalculo = $this->repartoIgual($claves);

            return;
        }

        $pesos = array_intersect_key($anteriores, array_flip($claves));
        $restante = round(100 - array_sum(array_map(fn ($peso) => $this->pesoNumerico($peso) ?? 0, $pesos)), 2);
        foreach (array_diff($claves, array_keys($pesos)) as $nueva) {
            $pesos[$nueva] = $restante > 0 ? $this->formatearPeso($restante) : '';
            $restante = 0;
        }
        $this->pesosCalculo = $pesos;
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, string>
     */
    protected function repartoIgual(array $claves): array
    {
        if ($claves === []) {
            return [];
        }

        $base = floor(10000 / count($claves)) / 100;
        $pesos = array_fill_keys($claves, $this->formatearPeso($base));
        $pesos[end($claves)] = $this->formatearPeso(100 - $base * (count($claves) - 1));

        return $pesos;
    }

    /** Peso escrito por el profesor («30», «33,33»); null si no es un número con hasta 2 decimales. */
    protected function pesoNumerico(string $peso): ?float
    {
        $peso = str_replace(',', '.', trim($peso));

        return preg_match('/^\d{1,3}(\.\d{1,2})?$/', $peso) ? (float) $peso : null;
    }

    protected function formatearPeso(float $peso): string
    {
        return rtrim(rtrim(number_format(round($peso, 2), 2, '.', ''), '0'), '.');
    }

    protected function mensajeTotal(float $faltante): string
    {
        return $faltante > 0
            ? 'Faltan '.$this->formatearPeso($faltante).' % para llegar a 100 %.'
            : 'Te pasas por '.$this->formatearPeso(-$faltante).' %. Ajusta los pesos.';
    }

    /**
     * Total en vivo del panel para la barra y el mensaje (no reemplaza la
     * validación de guardarCalculo).
     *
     * @return array{total: float, estado: string, mensaje: string}
     */
    protected function totalDelPanel(): array
    {
        $claves = $this->clavesSeleccionadas();
        $pesos = array_map(fn (string $clave) => $this->pesoNumerico($this->pesosCalculo[$clave] ?? ''), $claves);
        $total = round(array_sum(array_map(fn ($peso) => $peso ?? 0, $pesos)), 2);

        return match (true) {
            $claves === [] => ['total' => 0.0, 'estado' => 'error', 'mensaje' => 'Elige al menos una actividad.'],
            in_array(null, $pesos, true) || min($pesos) < 1 || max($pesos) > 100 => ['total' => $total, 'estado' => 'error', 'mensaje' => 'Usa un valor entre 1 y 100 en cada peso.'],
            abs($total - 100) >= 0.005 => ['total' => $total, 'estado' => $total < 100 ? 'aviso' : 'error', 'mensaje' => $this->mensajeTotal(round(100 - $total, 2))],
            default => ['total' => $total, 'estado' => 'ok', 'mensaje' => 'Suma 100 %. Listo para guardar.'],
        };
    }

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
            ->with(['modulo', 'rubrosEvaluacion'])
            ->when($this->tipo === 'sin_evaluacion', fn ($q) => $q->whereDoesntHave('rubrosEvaluacion'))
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
                'puedeEliminarse' => $tarea->entregas_count === 0 && $tarea->rubrosEvaluacion->isEmpty(),
                'motivoNoEliminar' => $tarea->rubrosEvaluacion->isNotEmpty() ? 'Está incluida en una evaluación: desvincúlala antes de eliminar.' : 'Tiene entregas: no se puede eliminar (puedes ocultarla desde Tareas).',
                'urlVer' => route('cursos.tareas.show', [$this->curso, $tarea]),
                'urlEditar' => route('cursos.tareas.editar', [$this->curso, $tarea, 'desde' => 'actividades']),
                'evaluacion' => $this->evaluacionDe($tarea->rubrosEvaluacion->first()),
            ]);

        $quizzes = $this->tipo === 'tareas' ? collect() : $this->curso->quizzes()
            ->with(['modulo', 'rubrosEvaluacion'])
            ->when($this->tipo === 'sin_evaluacion', fn ($q) => $q->whereDoesntHave('rubrosEvaluacion'))
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
                'puedeEliminarse' => $quiz->resultados_count === 0 && $quiz->rubrosEvaluacion->isEmpty(),
                'motivoNoEliminar' => $quiz->rubrosEvaluacion->isNotEmpty() ? 'Está incluido en una evaluación: desvincúlalo antes de eliminar.' : 'Tiene intentos de estudiantes: no se puede eliminar (puedes ocultarlo desde Quizzes).',
                'urlVer' => route('cursos.quizzes.show', [$this->curso, $quiz]),
                'urlEditar' => route('cursos.quizzes.editar', [$this->curso, $quiz, 'desde' => 'actividades']),
                'evaluacion' => $this->evaluacionDe($quiz->rubrosEvaluacion->first()),
            ]);

        return $tareas->concat($quizzes)
            ->sortBy([
                fn ($a, $b) => ($a['fecha'] === null) <=> ($b['fecha'] === null),
                fn ($a, $b) => ($a['fecha']?->getTimestamp() ?? 0) <=> ($b['fecha']?->getTimestamp() ?? 0),
                fn ($a, $b) => strcasecmp($a['titulo'], $b['titulo']),
            ])
            ->values();
    }

    /**
     * Datos de la insignia «Evaluación 1 · 30 %» de una fila.
     *
     * @return array{id: int, nombre: string, peso: float, aporte: float, examen: bool}|null
     */
    protected function evaluacionDe(?RubroEvaluacion $rubro): ?array
    {
        if ($rubro === null) {
            return null;
        }

        $peso = (float) $rubro->pivot->peso;

        return [
            'id' => $rubro->id,
            'nombre' => $rubro->nombre,
            'peso' => $peso,
            'aporte' => round($peso * (float) $rubro->peso / 100, 2),
            'examen' => $rubro->modo_calculo === ModoCalculo::Examen,
        ];
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

        return view('livewire.profesor.actividades', [
            'rubros' => $this->curso->esquemaEvaluacion()->with(['rubros.tareas', 'rubros.quizzes'])->first()?->rubros ?? collect(),
            'tareasDisponibles' => $this->mostrarCalculo ? $this->curso->tareas()->with(['modulo', 'rubrosEvaluacion'])->orderBy('titulo')->get() : collect(),
            'quizzesDisponibles' => $this->mostrarCalculo ? $this->curso->quizzes()->with(['modulo', 'rubrosEvaluacion'])->orderBy('tema')->get() : collect(),
            'totalCalculo' => $this->mostrarCalculo && $this->modoCalculo === ModoCalculo::Promedio->value ? $this->totalDelPanel() : null,
            'actividades' => $actividades,
            'totales' => [
                'tareas' => $this->curso->tareas()->count(),
                'quizzes' => $this->curso->quizzes()->count(),
                'sin_evaluacion' => $this->curso->tareas()->whereDoesntHave('rubrosEvaluacion')->count()
                    + $this->curso->quizzes()->whereDoesntHave('rubrosEvaluacion')->count(),
            ],
            'editable' => ! $this->curso->estaFinalizado(),
        ])->title("Actividades — {$this->curso->nombre}");
    }
}
