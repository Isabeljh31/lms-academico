<?php

namespace App\Services\Reportes;

use App\Models\Curso;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Calendario\ColoresCursos;
use App\Services\Calendario\SeccionesProfesor;
use App\Services\ConfiguracionPlataforma;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Datos de la pantalla Cursos del Profesor y del encabezado del detalle de un
 * curso: avance, matriculados, por calificar, próximo cierre y un aviso.
 *
 * Cada dato sale de UNA consulta agrupada para todos los cursos (sin N+1) y
 * reutiliza los criterios del Panel (PanelProfesor): las entregas grupales
 * cuentan una vez por grupo. "Para hacer" y el aviso de cada tarjeta salen de
 * paraHacer(), con su propia prioridad (semana sin contenido, evaluación sin
 * actividades, por calificar).
 * La semana actual es la del detalle del curso: la semana cuyo rango
 * (Curso::rangoSemana) contiene el momento actual.
 */
class CursosProfesor
{
    /** Etiqueta y estilo de la pastilla de cada estado del curso (pantalla Cursos y encabezado del detalle). */
    public const ESTADOS_CURSO = [
        'publicado' => ['Publicado', 'bg-exito-base/15 text-exito-oscuro'],
        'borrador' => ['Borrador', 'bg-amber-100 text-amber-800'],
        'despublicado' => ['Despublicado', 'bg-amber-100 text-amber-800'],
        'archivado' => ['Archivado', 'bg-celeste text-slate-600'],
    ];

    /** Etiqueta de cada modalidad (Curso::MODALIDADES). */
    public const MODALIDADES = ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'remoto' => 'Remoto'];

    public function __construct(
        protected PanelProfesor $panel,
        protected ColoresCursos $colores,
    ) {}

    /**
     * Todos los cursos que dicta el profesor (titular o co-docente), en
     * cualquier estado, con lo que la pantalla necesita ya cargado.
     *
     * @return Collection<int, Curso>
     */
    public function cursos(User $profesor): Collection
    {
        return Curso::dictadosPor($profesor)
            ->withCount('inscripcionesActivas')
            ->with(['profesor', 'profesoresAdicionales', 'esquemaEvaluacion.rubros.tareas', 'esquemaEvaluacion.rubros.quizzes'])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();
    }

    /**
     * Resumen de cada curso, calculado con consultas agrupadas.
     *
     * @param  Collection<int, Curso>  $cursos  de cursos(), o con las mismas relaciones cargadas
     * @param  Collection<int, Curso>  $todos  todos los cursos del profesor (para colores y secciones)
     * @param  bool  $conAvisos  false si no se muestran "Para hacer" ni el aviso (los rubros pueden venir sin sus actividades cargadas)
     * @return array<int, array{color: string, iniciales: string, codigo: ?string, docentes: string, semanas: int, avance: array{estado: string, porcentaje: int, texto: string, detalle: ?string}, pendientes: int, mas_antigua: ?Carbon, cierre: ?array{fecha: Carbon, titulo: string, semana: ?int, tipo: string, texto: string}, semana_actual: ?string, para_hacer: list<array{tipo: string, texto: string, accion: string, url: string}>, aviso: ?string}>
     */
    public function resumen(Collection $cursos, Collection $todos, ?Carbon $ahora = null, bool $conAvisos = true): array
    {
        $ahora ??= now();
        $vigentes = $cursos->reject(fn (Curso $curso) => $curso->estaFinalizado())->values();
        $vigenteIds = collect($vigentes->modelKeys());

        $semanas = $this->panel->semanasConLecciones(collect($cursos->modelKeys()));
        $semanasPorCurso = $semanas->groupBy('curso_id');
        $actividades = $this->panel->pendientesPorActividad($vigenteIds)->groupBy('curso_id');
        $cierres = $this->proximosCierres($vigenteIds, $semanas, $ahora);

        // Mismo color que el Panel y el Calendario (ColoresCursos sobre los cursos vigentes); un curso archivado va en gris.
        $colores = $this->colores->asignar($todos->reject(fn (Curso $curso) => $curso->estaFinalizado())->values());
        $secciones = new SeccionesProfesor($todos);

        return $cursos->mapWithKeys(function (Curso $curso) use ($ahora, $semanasPorCurso, $actividades, $cierres, $conAvisos, $colores, $secciones) {
            $semanasCurso = $semanasPorCurso->get($curso->id, collect());
            $total = $this->panel->totalSemanas($curso, $semanasCurso);
            $pendientes = $actividades->get($curso->id, collect());
            $porCalificar = (int) $pendientes->sum('pendientes');
            $rubros = $curso->relationLoaded('esquemaEvaluacion') ? ($curso->esquemaEvaluacion?->rubros ?? collect()) : collect();
            $cierre = $curso->estaFinalizado() ? null : ($cierres->get($curso->id) ?? $this->cierreDeEvaluacion($curso, $rubros, $ahora));
            $actual = $curso->estaFinalizado() ? null : self::semanaActual($curso, $total, $ahora);
            $paraHacer = $conAvisos ? $this->paraHacer($curso, $total, $actual, $semanasCurso, $rubros, $porCalificar, $ahora) : [];

            return [$curso->id => [
                'color' => $colores[$curso->id] ?? ColoresCursos::NEUTRO,
                'iniciales' => PanelProfesor::iniciales($curso->nombre),
                'codigo' => $curso->codigo ?: ($secciones->tieneVariasSecciones($curso->id) ? $secciones->codigo($curso->id) : null),
                'docentes' => $curso->docentes()->pluck('name')->join(', '),
                'semanas' => $total,
                'avance' => $this->avance($curso, $total, $ahora),
                'pendientes' => $porCalificar,
                'mas_antigua' => $pendientes->min('mas_antigua'),
                'cierre' => $cierre ? [...$cierre, 'texto' => $this->textoCierre($cierre, $ahora)] : null,
                'semana_actual' => $actual ? 'Semana '.$actual.' · '.$this->rangoCorto($curso, $actual) : null,
                'para_hacer' => $paraHacer,
                'aviso' => $conAvisos ? $this->aviso($curso, $paraHacer) : null,
            ]];
        })->all();
    }

    /**
     * «Práctica 1 · vence el 08/11 (en 3 días)», «… · vence mañana» o, para una
     * evaluación del Supervisor, «Evaluación 2 · termina el 22/11 (en 17 días)».
     *
     * @param  array{fecha: Carbon, titulo: string, tipo: string}  $cierre
     */
    public function textoCierre(array $cierre, ?Carbon $ahora = null): string
    {
        $verbo = $cierre['tipo'] === 'evaluacion' ? 'termina' : 'vence';
        $dias = PanelProfesor::diasHasta($cierre['fecha'], $ahora);

        $cuando = match (true) {
            $dias <= 0 => "{$verbo} hoy",
            $dias === 1 => "{$verbo} mañana",
            default => "{$verbo} el ".$cierre['fecha']->formatoDiaMes()." (en {$dias} días)",
        };

        return $cierre['titulo'].' · '.$cuando;
    }

    /**
     * Si no hay tareas ni quizzes por cerrar: el fin (domingo) de la semana de
     * la próxima evaluación que definió el Supervisor, solo lectura y sin
     * consultas (los rubros ya vienen cargados).
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @return array{fecha: Carbon, titulo: string, semana: ?int, tipo: string}|null
     */
    public function cierreDeEvaluacion(Curso $curso, Collection $rubros, ?Carbon $ahora = null): ?array
    {
        $ahora ??= now();

        if (! $curso->fecha_inicio) {
            return null;
        }

        return $rubros
            ->filter(fn (RubroEvaluacion $rubro) => $rubro->semana)
            ->map(fn (RubroEvaluacion $rubro) => ['fecha' => $curso->rangoSemana($rubro->semana)[1], 'titulo' => $rubro->nombre, 'semana' => $rubro->semana, 'tipo' => 'evaluacion'])
            ->filter(fn (array $cierre) => $cierre['fecha']->gt($ahora))
            ->sortBy(fn (array $cierre) => $cierre['fecha']->getTimestamp())
            ->first();
    }

    /**
     * Lista "Para hacer" del curso (máximo 3, la más urgente primero), cada
     * ítem con su acción hacia una pantalla que ya existe:
     *
     * 1. La semana actual sin contenido o, si la actual ya tiene, la siguiente
     *    sin contenido («sin contenido»: ni lecciones, ni tareas, ni quizzes,
     *    el mismo criterio que el detalle). Antes de empezar el curso, la Semana 1.
     * 2. Evaluaciones del Supervisor sin actividades asignadas cuya semana aún
     *    no terminó (RubroEvaluacion::estadoCalculo() distinto de «lista»).
     * 3. Entregas por calificar.
     *
     * Vacía = todo al día. Sin consultas: trabaja con lo ya cargado.
     *
     * @param  Collection<int, Modulo>  $semanas  semanasConLecciones() del curso
     * @param  Collection<int, RubroEvaluacion>  $rubros  con tareas y quizzes cargados
     * @return list<array{tipo: string, texto: string, accion: string, url: string}>
     */
    public function paraHacer(Curso $curso, int $total, ?int $actual, Collection $semanas, Collection $rubros, int $porCalificar, ?Carbon $ahora = null): array
    {
        $ahora ??= now();

        if ($curso->estaFinalizado()) {
            return [];
        }

        $items = [];
        $elementos = $semanas->groupBy('semana')->map(fn (Collection $modulos) => $modulos->sum(fn (Modulo $m) => $m->lecciones_count + ($m->tareas_count ?? 0) + ($m->quizzes_count ?? 0)));
        $vacia = fn (int $numero) => ($elementos[$numero] ?? 0) === 0;
        $agregar = fn (int $numero) => route('cursos.show', ['curso' => $curso, 'semana' => $numero]);

        if ($actual !== null && $vacia($actual)) {
            $items[] = ['tipo' => 'semana_actual', 'texto' => "La Semana {$actual} (actual) no tiene contenido", 'accion' => 'Agregar contenido', 'url' => $agregar($actual)];
        } else {
            $siguiente = match (true) {
                $actual !== null => $actual + 1,
                $curso->fecha_inicio !== null && $ahora->lt($curso->rangoSemana(1)[0]) => 1,
                default => null,
            };

            if ($siguiente !== null && $siguiente <= $total && $vacia($siguiente)) {
                $inicio = $curso->rangoSemana($siguiente)[0];
                $items[] = ['tipo' => 'semana_siguiente', 'texto' => "La Semana {$siguiente} empieza el ".$inicio->copy()->locale('es')->isoFormat('dddd').' '.$inicio->formatoDiaMes().' y aún no tiene contenido', 'accion' => 'Agregar contenido', 'url' => $agregar($siguiente)];
            }
        }

        if ($curso->fecha_inicio) {
            $rubros->filter(fn (RubroEvaluacion $rubro) => $rubro->semana && $rubro->estadoCalculo() !== 'lista' && $curso->rangoSemana($rubro->semana)[1]->endOfDay()->gt($ahora))
                ->sortBy('semana')
                ->each(function (RubroEvaluacion $rubro) use (&$items, $curso) {
                    $items[] = ['tipo' => 'evaluacion', 'texto' => $rubro->nombre.' · '.$this->rangoCorto($curso, $rubro->semana).' · sin actividades', 'accion' => 'Crear actividad', 'url' => route('cursos.actividades', $curso)];
                });
        }

        if ($porCalificar > 0) {
            $items[] = ['tipo' => 'calificar', 'texto' => ($porCalificar === 1 ? '1 entrega' : "{$porCalificar} entregas").' por calificar', 'accion' => 'Calificar', 'url' => route('docente.por-calificar')];
        }

        return array_slice($items, 0, 3);
    }

    /** «02/11 – 08/11»: del lunes al domingo de la semana N del curso. */
    protected function rangoCorto(Curso $curso, int $semana): string
    {
        [$inicio, $fin] = $curso->rangoSemana($semana);

        return $inicio->formatoDiaMes().' – '.$fin->formatoDiaMes();
    }

    /**
     * Semana en curso: la primera cuyo rango contiene el momento actual, el
     * mismo cálculo que abre la "Semana actual" en el detalle del curso.
     */
    public static function semanaActual(Curso $curso, int $totalSemanas, ?Carbon $ahora = null): ?int
    {
        $ahora ??= now();

        return collect(range(1, max(1, $totalSemanas)))->first(function (int $semana) use ($curso, $ahora) {
            $rango = $curso->rangoSemana($semana);

            return $rango && $ahora->between($rango[0], $rango[1]);
        });
    }

    /**
     * Avance del curso por semanas: «Semana X de N» con su porcentaje, o si
     * aún no empieza, ya terminó, está archivado o no tiene fecha de inicio.
     *
     * @return array{estado: string, porcentaje: int, texto: string, detalle: ?string}
     */
    public function avance(Curso $curso, int $totalSemanas, ?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $finDeClases = fn () => $curso->fecha_fin && $curso->fecha_fin->lte($ahora)
            ? $curso->fecha_fin
            : ($curso->rangoSemana($totalSemanas)[1] ?? $curso->fecha_fin);

        if ($curso->estaFinalizado()) {
            $fin = $curso->fecha_inicio ? $finDeClases() : $curso->fecha_fin;

            return ['estado' => 'finalizado', 'porcentaje' => 100, 'texto' => $fin ? 'Finalizado el '.$this->fecha($fin) : 'Curso finalizado', 'detalle' => '100 %'];
        }

        if (! $curso->fecha_inicio) {
            return ['estado' => 'sin_fechas', 'porcentaje' => 0, 'texto' => 'Sin fecha de inicio', 'detalle' => null];
        }

        $actual = self::semanaActual($curso, $totalSemanas, $ahora);

        if ($actual !== null) {
            $porcentaje = (int) round($actual / $totalSemanas * 100);

            return ['estado' => 'en_curso', 'porcentaje' => $porcentaje, 'texto' => "Semana {$actual} de {$totalSemanas}", 'detalle' => "{$porcentaje} %"];
        }

        if ($ahora->lt($curso->rangoSemana(1)[0])) {
            return ['estado' => 'por_empezar', 'porcentaje' => 0, 'texto' => 'Empieza el '.$this->fecha($curso->fecha_inicio),
                'detalle' => PanelProfesor::textoDias(PanelProfesor::diasHasta($curso->fecha_inicio, $ahora))];
        }

        return ['estado' => 'terminado', 'porcentaje' => 100, 'texto' => 'Terminó el '.$this->fecha($finDeClases()), 'detalle' => '100 %'];
    }

    /**
     * Siguiente fecha límite futura de cada curso: la tarea o el quiz visible
     * para los estudiantes que cierra antes. Una sola consulta (UNION) para
     * todos los cursos.
     *
     * @param  Collection<int, int>  $cursoIds
     * @param  Collection<int, Modulo>  $semanas  semanasConLecciones(), para saber en qué semana está la actividad
     * @return Collection<int, array{fecha: Carbon, titulo: string, semana: ?int}> curso_id => cierre
     */
    public function proximosCierres(Collection $cursoIds, Collection $semanas, ?Carbon $ahora = null): Collection
    {
        $ahora ??= now();

        if ($cursoIds->isEmpty()) {
            return collect();
        }

        $tareas = Tarea::query()->whereIn('curso_id', $cursoIds)->visiblesParaEstudiante()
            ->where('fecha_entrega', '>', $ahora)
            ->select(['curso_id', 'modulo_id', 'titulo', 'fecha_entrega as fecha'])
            ->toBase();

        $quizzes = Quiz::query()->whereIn('curso_id', $cursoIds)->visiblesParaEstudiante()
            ->where('fecha_limite', '>', $ahora)
            ->select(['curso_id', 'modulo_id', 'tema as titulo', 'fecha_limite as fecha'])
            ->toBase();

        $semanaDeModulo = $semanas->pluck('semana', 'id');

        return $tareas->unionAll($quizzes)
            ->orderBy('fecha')
            ->orderBy('titulo')
            ->get()
            ->groupBy('curso_id')
            ->map(function (Collection $filas) use ($semanaDeModulo) {
                $fila = $filas->first();

                return [
                    'fecha' => Carbon::parse($fila->fecha),
                    'titulo' => (string) $fila->titulo,
                    'semana' => $fila->modulo_id ? $semanaDeModulo->get($fila->modulo_id) : null,
                    'tipo' => 'actividad',
                ];
            })
            ->mapWithKeys(fn (array $cierre, $cursoId) => [(int) $cursoId => $cierre]);
    }

    /** Fecha completa con el formato configurado en la plataforma. */
    public function fecha(Carbon $fecha): string
    {
        return $fecha->format(app(ConfiguracionPlataforma::class)->formatoFecha());
    }

    /**
     * El único aviso de la tarjeta normal (3 o más cursos), con la misma
     * prioridad que "Para hacer": el primer ítem que no sea calificar (eso ya
     * se ve en la cifra y en el botón "Calificar N"); si no hay, que el curso
     * aún no es visible para los estudiantes.
     *
     * @param  list<array{tipo: string, texto: string}>  $paraHacer
     */
    protected function aviso(Curso $curso, array $paraHacer): ?string
    {
        $primero = collect($paraHacer)->first(fn (array $item) => $item['tipo'] !== 'calificar');

        if ($primero) {
            return $primero['texto'];
        }

        return match ($curso->estado) {
            'borrador' => 'Aún es un borrador: los estudiantes no lo ven',
            'despublicado' => 'Está despublicado: los estudiantes no lo ven',
            default => null,
        };
    }
}
