<?php

namespace App\Services\Reportes;

use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Evaluacion\CalculadoraNotas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Datos del Panel del Profesor (ERS RF-88): un resumen con tope de lo que el
 * profesor tiene que hacer ahora y lo que se le viene.
 *
 * No calcula notas ni califica: el promedio sale de CalculadoraNotas y cada
 * acción enlaza a la pantalla que ya existe. Las consultas son agregadas, de
 * modo que su número no depende de cuántos cursos o actividades haya (salvo
 * el promedio, que reutiliza el cálculo existente curso por curso).
 */
class PanelProfesor
{
    /** Cierres y evaluaciones que se muestran: los de los próximos N días. */
    public const HORIZONTE_DIAS = 30;

    /** Semanas sin contenido que se avisan: las que empiezan en los próximos N días. */
    public const DIAS_CONTENIDO = 14;

    /** Desde cuántos días faltantes la pastilla deja de ser de alerta. */
    public const DIAS_ALERTA = 3;

    public function __construct(protected CalculadoraNotas $calculadora) {}

    /**
     * Cursos vigentes (no archivados) que dicta el profesor, como titular o
     * co-docente, con lo que el Panel necesita ya cargado.
     *
     * @return Collection<int, Curso>
     */
    public function cursos(User $profesor): Collection
    {
        return Curso::dictadosPor($profesor)
            ->where('estado', '!=', 'archivado')
            ->withCount('inscripcionesActivas')
            ->with([
                'inscripcionesActivas:id,curso_id,estudiante_id',
                'esquemaEvaluacion.rubros.tareas',
                'esquemaEvaluacion.rubros.quizzes',
            ])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();
    }

    /**
     * Actividades con entregas por calificar, la más antigua primero. Las
     * entregas grupales cuentan una vez por grupo.
     *
     * @param  Collection<int, int>|Builder  $cursoIds
     * @return Collection<int, array{tarea_id: int, curso_id: int, titulo: string, grupal: bool, pendientes: int, mas_antigua: Carbon}>
     */
    public function pendientesPorActividad(Collection|Builder $cursoIds): Collection
    {
        if ($cursoIds instanceof Collection && $cursoIds->isEmpty()) {
            return collect();
        }

        return Entrega::query()
            ->join('tareas', 'tareas.id', '=', 'entregas.tarea_id')
            ->whereIn('tareas.curso_id', $cursoIds)
            ->where('entregas.estado', 'entregado')
            ->groupBy('tareas.id', 'tareas.curso_id', 'tareas.titulo', 'tareas.tipo_entrega')
            ->selectRaw('tareas.id as tarea_id, tareas.curso_id, tareas.titulo, tareas.tipo_entrega,
                count(distinct entregas.grupo_id) + sum(case when entregas.grupo_id is null then 1 else 0 end) as pendientes,
                min(entregas.updated_at) as mas_antigua')
            ->orderBy('mas_antigua')
            ->orderBy('tareas.id')
            ->toBase()
            ->get()
            ->map(fn (object $fila) => [
                'tarea_id' => (int) $fila->tarea_id,
                'curso_id' => (int) $fila->curso_id,
                'titulo' => (string) $fila->titulo,
                'grupal' => $fila->tipo_entrega === 'grupal',
                'pendientes' => (int) $fila->pendientes,
                'mas_antigua' => Carbon::parse($fila->mas_antigua),
            ]);
    }

    /** Total por calificar del profesor (una vez por grupo), en una sola consulta. */
    public function totalPorCalificar(User $profesor): int
    {
        $cursos = Curso::dictadosPor($profesor)->where('estado', '!=', 'archivado')->select('id');

        return (int) $this->pendientesPorActividad($cursos)->sum('pendientes');
    }

    /**
     * Quizzes visibles para los estudiantes en los cursos del profesor.
     *
     * @param  Collection<int, int>  $cursoIds
     * @return Collection<int, Quiz>
     */
    public function quizzesVisibles(Collection $cursoIds): Collection
    {
        if ($cursoIds->isEmpty()) {
            return collect();
        }

        return Quiz::whereIn('curso_id', $cursoIds)->visiblesParaEstudiante()
            ->get(['id', 'curso_id', 'tema', 'fecha_inicio', 'fecha_limite']);
    }

    /**
     * Semanas de contenido de los cursos (sin la sección de prácticas), con
     * cuántas lecciones tiene cada una.
     *
     * @param  Collection<int, int>  $cursoIds
     * @return Collection<int, Modulo>
     */
    public function semanasConLecciones(Collection $cursoIds): Collection
    {
        if ($cursoIds->isEmpty()) {
            return collect();
        }

        return Modulo::whereIn('curso_id', $cursoIds)
            ->whereNotNull('semana')
            // tareas y quizzes: para la línea de semanas de la pantalla Cursos (misma consulta, subconsultas de conteo).
            ->withCount(['lecciones', 'tareas', 'quizzes'])
            ->get(['id', 'curso_id', 'semana']);
    }

    /**
     * Promedio a la fecha de cada curso con el cálculo existente
     * (CalculadoraNotas::resumenDelCurso), igual que el Panel anterior. Un
     * curso sin evaluaciones o sin estudiantes no tiene notas.
     *
     * @param  Collection<int, Curso>  $cursos  con inscripcionesActivas y esquemaEvaluacion.rubros cargados
     * @return array<int, float|null> curso_id => promedio
     */
    public function promediosPorCurso(Collection $cursos): array
    {
        return $cursos->mapWithKeys(function (Curso $curso) {
            $estudianteIds = $curso->inscripcionesActivas->pluck('estudiante_id');

            if ($estudianteIds->isEmpty() || ($curso->esquemaEvaluacion?->rubros ?? collect())->isEmpty()) {
                return [$curso->id => null];
            }

            $notas = collect($this->calculadora->resumenDelCurso($curso, $estudianteIds))
                ->pluck('rendimiento')
                ->filter(fn ($nota) => $nota !== null);

            return [$curso->id => $notas->isNotEmpty() ? round($notas->avg(), 1) : null];
        })->all();
    }

    /**
     * Promedio general: el promedio de los promedios de cada curso con notas
     * (mismo criterio que el Panel anterior).
     *
     * @param  array<int, float|null>  $promedios
     */
    public function promedioGeneral(array $promedios): ?float
    {
        $conNotas = collect($promedios)->filter(fn ($promedio) => $promedio !== null);

        return $conNotas->isNotEmpty() ? round($conNotas->avg(), 1) : null;
    }

    /**
     * Total de semanas del curso sin consultar: la duración definida (16 si no
     * la tiene) o la última semana con contenido, igual que Curso::totalSemanas().
     *
     * @param  Collection<int, Modulo>  $semanas  semanas del curso
     */
    public function totalSemanas(Curso $curso, Collection $semanas): int
    {
        return max((int) ($curso->semanas_duracion ?: Curso::SEMANAS_PREDETERMINADAS), (int) $semanas->max('semana'));
    }

    /**
     * Próximas fechas (solo futuras), la más cercana primero: cierres de
     * tareas y quizzes, semanas que empiezan pronto sin contenido y
     * evaluaciones cercanas que aún no tienen actividades asignadas.
     *
     * @param  Collection<int, Curso>  $cursos
     * @param  Collection<int, Quiz>  $quizzes
     * @param  Collection<int, Modulo>  $semanas
     * @return Collection<int, array{clave: string, tipo: string, curso_id: int, fecha: Carbon, dias: int, titulo: string, prefijo: ?string, accion: string, url: string, icono: string}>
     */
    public function proximasFechas(Collection $cursos, Collection $quizzes, Collection $semanas, ?Carbon $ahora = null): Collection
    {
        $ahora ??= now();
        $limite = $ahora->copy()->addDays(self::HORIZONTE_DIAS)->endOfDay();
        $cursoIds = $cursos->modelKeys();
        $items = collect();

        $tareas = $cursoIds === [] ? collect() : Tarea::whereIn('curso_id', $cursoIds)
            ->visiblesParaEstudiante()
            ->where('fecha_entrega', '>', $ahora)
            ->where('fecha_entrega', '<=', $limite)
            ->get(['id', 'curso_id', 'titulo', 'fecha_entrega']);

        foreach ($tareas as $tarea) {
            $items->push($this->item('cierre', 'tarea-'.$tarea->id, $tarea->curso_id, $tarea->fecha_entrega, $ahora,
                'La tarea «'.$tarea->titulo.'» cierra el '.$this->diaYHora($tarea->fecha_entrega),
                'Ver', route('cursos.tareas.show', [$tarea->curso_id, $tarea->id]), 'tarea'));
        }

        foreach ($quizzes as $quiz) {
            if (! $quiz->fecha_limite || $quiz->fecha_limite->lte($ahora) || $quiz->fecha_limite->gt($limite)) {
                continue;
            }

            $items->push($this->item('cierre', 'quiz-'.$quiz->id, $quiz->curso_id, $quiz->fecha_limite, $ahora,
                'El quiz «'.$quiz->tema.'» cierra el '.$this->diaYHora($quiz->fecha_limite),
                'Ver', route('cursos.actividades', $quiz->curso_id), 'quiz'));
        }

        return $items->concat($this->avisos($cursos, $semanas, $ahora))->sortBy([
            fn (array $a, array $b) => $a['fecha'] <=> $b['fecha'],
            fn (array $a, array $b) => strcmp($a['clave'], $b['clave']),
        ])->values();
    }

    /**
     * Avisos de preparación de cada curso: semanas que empiezan en los
     * próximos DIAS_CONTENIDO días sin lecciones y evaluaciones cercanas sin
     * actividades asignadas. Sin consultas: trabaja con lo ya cargado.
     *
     * @param  Collection<int, Curso>  $cursos  con esquemaEvaluacion.rubros (tareas y quizzes) cargados
     * @param  Collection<int, Modulo>  $semanas  semanasConLecciones() de esos cursos
     * @return Collection<int, array<string, mixed>>
     */
    protected function avisos(Collection $cursos, Collection $semanas, ?Carbon $ahora = null): Collection
    {
        $ahora ??= now();
        $limite = $ahora->copy()->addDays(self::HORIZONTE_DIAS)->endOfDay();
        $items = collect();
        $semanasPorCurso = $semanas->groupBy('curso_id');
        $limiteContenido = $ahora->copy()->addDays(self::DIAS_CONTENIDO)->endOfDay();

        foreach ($cursos as $curso) {
            if (! $curso->fecha_inicio) {
                continue;
            }

            $semanasCurso = $semanasPorCurso->get($curso->id, collect());
            $total = $this->totalSemanas($curso, $semanasCurso);
            $leccionesPorSemana = $semanasCurso->groupBy('semana')->map(fn (Collection $modulos) => $modulos->sum('lecciones_count'));

            for ($numero = 1; $numero <= $total; $numero++) {
                $inicio = $this->inicioDeSemana($curso, $numero);

                if ($inicio->gt($limiteContenido) || ($curso->fecha_fin && $inicio->gt($curso->fecha_fin))) {
                    break;
                }

                if ($inicio->lte($ahora) || ($leccionesPorSemana[$numero] ?? 0) > 0) {
                    continue;
                }

                $items->push($this->item('contenido', 'semana-'.$curso->id.'-'.$numero, $curso->id, $inicio, $ahora,
                    'La semana '.$numero.' empieza el '.$this->dia($inicio).' y aún no tiene contenido',
                    'Subir contenido', route('cursos.show', $curso), 'calendario'));
            }

            foreach ($curso->esquemaEvaluacion?->rubros ?? collect() as $rubro) {
                $item = $this->evaluacionSinActividades($curso, $rubro, $ahora, $limite);

                if ($item) {
                    $items->push($item);
                }
            }
        }

        return $items;
    }

    /**
     * Evaluación cuya semana está por llegar (o en curso) y que aún no tiene
     * actividades: el mismo criterio que el estado "Falta definir" de
     * AvanceEvaluaciones (RubroEvaluacion::estadoCalculo() distinto de «lista»),
     * sin consultas por curso.
     *
     * @return array<string, mixed>|null
     */
    protected function evaluacionSinActividades(Curso $curso, RubroEvaluacion $rubro, Carbon $ahora, Carbon $limite): ?array
    {
        if (! $rubro->semana || $rubro->estadoCalculo() === 'lista') {
            return null;
        }

        [$inicio, $fin] = $curso->rangoSemana($rubro->semana);
        $fin = $fin->copy()->endOfDay();

        if ($fin->lte($ahora) || $inicio->gt($limite)) {
            return null;
        }

        $empezo = $inicio->lte($ahora);

        return $this->item('evaluacion', 'rubro-'.$rubro->id, $curso->id, $empezo ? $fin : $inicio, $ahora,
            'La evaluación «'.$rubro->nombre.'» (semana '.$rubro->semana.') aún no tiene actividades asignadas',
            'Asignar actividades', route('cursos.actividades', $curso), 'check-circulo', $empezo ? 'termina' : null);
    }

    /**
     * Comunicados institucionales para el profesor (la misma fuente que el
     * Panel anterior): los urgentes primero y luego los más recientes, con los
     * contadores de cada filtro.
     *
     * @return array{lista: Collection<int, Comunicado>, total: int, urgentes: int, institucionales: int}
     */
    public function comunicados(string $filtro, int $cantidad): array
    {
        $base = fn () => Comunicado::activos()->whereNull('curso_id')->paraRol('profesor');

        $conteo = $base()->toBase()
            ->selectRaw("count(*) as total, sum(case when prioridad = 'urgente' then 1 else 0 end) as urgentes")
            ->first();
        $total = (int) ($conteo->total ?? 0);
        $urgentes = (int) ($conteo->urgentes ?? 0);

        $lista = $total === 0 ? collect() : $base()
            ->when($filtro === 'urgentes', fn ($q) => $q->where('prioridad', 'urgente'))
            ->when($filtro === 'institucionales', fn ($q) => $q->where('prioridad', '!=', 'urgente'))
            // Los urgentes siempre primero; después, el resto del más reciente al más antiguo.
            ->orderByRaw("case when prioridad = 'urgente' then 0 else 1 end")
            ->latest()
            ->latest('id')
            ->take($cantidad)
            ->get();

        return ['lista' => $lista, 'total' => $total, 'urgentes' => $urgentes, 'institucionales' => $total - $urgentes];
    }

    /** Días que faltan (en la zona horaria de la plataforma), contando por fecha. */
    public static function diasHasta(Carbon $fecha, ?Carbon $ahora = null): int
    {
        $ahora ??= now();

        return (int) $ahora->copy()->startOfDay()->diffInDays($fecha->copy()->startOfDay(), false);
    }

    /** «hoy», «mañana» o «en N días» (o con «termina» delante). */
    public static function textoDias(int $dias, ?string $prefijo = null): string
    {
        $texto = match (true) {
            $dias <= 0 => 'hoy',
            $dias === 1 => 'mañana',
            default => "en {$dias} días",
        };

        return $prefijo ? "{$prefijo} {$texto}" : $texto;
    }

    /** «hoy», «hace 1 día» o «hace N días». */
    public static function textoAntiguedad(Carbon $fecha, ?Carbon $ahora = null): string
    {
        $dias = -self::diasHasta($fecha, $ahora);

        return match (true) {
            $dias <= 0 => 'hoy',
            $dias === 1 => 'hace 1 día',
            default => "hace {$dias} días",
        };
    }

    /** Iniciales del curso para su distintivo: «Matemática Básica» → «MB». */
    public static function iniciales(string $nombre): string
    {
        $palabras = collect(preg_split('/\s+/u', trim($nombre)) ?: [])
            ->filter(fn (string $palabra) => mb_strlen($palabra) > 2 || is_numeric($palabra) || preg_match('/^[IVX]+$/u', $palabra))
            ->values();

        if ($palabras->isEmpty()) {
            return mb_strtoupper(mb_substr(trim($nombre), 0, 2)) ?: '?';
        }

        return mb_strtoupper($palabras->take(2)->map(fn (string $palabra) => mb_substr($palabra, 0, 1))->implode(''));
    }

    /** Primer día de la semana N del curso (la semana 1 empieza en la fecha de inicio). */
    protected function inicioDeSemana(Curso $curso, int $numero): Carbon
    {
        $inicio = $curso->rangoSemana($numero)[0];

        return $numero === 1 ? $curso->fecha_inicio->copy()->startOfDay()->max($inicio) : $inicio;
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(string $tipo, string $clave, int $cursoId, Carbon $fecha, Carbon $ahora, string $titulo, string $accion, string $url, string $icono, ?string $prefijo = null): array
    {
        return [
            'clave' => $clave,
            'tipo' => $tipo,
            'curso_id' => $cursoId,
            'fecha' => $fecha,
            'dias' => self::diasHasta($fecha, $ahora),
            'titulo' => $titulo,
            'prefijo' => $prefijo,
            'accion' => $accion,
            'url' => $url,
            'icono' => $icono,
        ];
    }

    protected function dia(Carbon $fecha): string
    {
        return $fecha->copy()->locale('es')->isoFormat('dddd').' '.$fecha->formatoDiaMes();
    }

    protected function diaYHora(Carbon $fecha): string
    {
        return $this->dia($fecha).' a las '.$fecha->format('H:i');
    }
}
