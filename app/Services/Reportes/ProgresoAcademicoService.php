<?php

namespace App\Services\Reportes;

use App\Models\Curso;
use App\Models\Leccion;
use App\Models\LeccionCompletada;
use App\Models\Modulo;
use App\Models\ResultadoQuiz;
use App\Models\User;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Evaluacion\NotasDeEvaluacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Progreso académico y reportes (ERS 3.8 y 3.14).
 *
 * - RF-66: el avance de un estudiante en un curso es el porcentaje de
 *   actividades completadas sobre las publicadas: lecciones completadas,
 *   tareas entregadas y quizzes rendidos.
 * - RF-67: cada módulo se da por completado según la condición que definió
 *   el Profesor (estadoModulos); cada lección, al marcarla o al abrir su recurso.
 * - RF-68/RF-69: progreso por curso para el estudiante y promedio para el profesor.
 * - RF-89: reporte por curso (progreso promedio, promedio de notas, tasa de
 *   aprobación, entregas y estudiantes con bajo rendimiento).
 * Las notas de tareas y quizzes se muestran como práctica y nunca se mezclan
 * con la Nota Final ponderada (RF-48).
 */
class ProgresoAcademicoService
{
    /** En un curso ya finalizado, un avance menor a este (%) se considera bajo rendimiento. */
    public const AVANCE_MINIMO = 50;

    /** Durante el periodo, desde esta cantidad de tareas vencidas sin entregar hay bajo rendimiento. */
    public const TAREAS_VENCIDAS_MAXIMAS = 2;

    public function __construct(protected CalculadoraNotas $calculadora) {}

    /**
     * Tareas y quizzes (práctica) de un estudiante en un curso.
     */
    public function progresoEstudiante(User $estudiante, Curso $curso): array
    {
        $tareas = $curso->tareas()
            ->visiblesParaEstudiante()
            ->with(['entregas' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
            ->get()
            ->map(function ($tarea) {
                $entrega = $tarea->entregas->first();

                return [
                    'titulo' => $tarea->titulo,
                    'peso' => (float) $tarea->peso_nota,
                    'nota' => $entrega?->nota !== null ? (float) $entrega->nota : null,
                    'estado' => $tarea->estadoPara($entrega),
                ];
            });

        $quizzes = $curso->quizzes()
            ->visiblesParaEstudiante()
            ->with(['resultados' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
            ->get()
            ->map(fn ($quiz) => [
                'tema' => $quiz->tema,
                'puntaje' => $quiz->resultados->isNotEmpty() ? (float) $quiz->resultados->max('puntaje') : null,
            ]);

        $tareasCalificadas = $tareas->filter(fn ($t) => $t['nota'] !== null);
        $sumaPesos = $tareasCalificadas->sum('peso');
        $quizzesCalificados = $quizzes->filter(fn ($q) => $q['puntaje'] !== null);

        return [
            'curso' => $curso,
            'tareas' => $tareas,
            'quizzes' => $quizzes,
            'promedio_tareas' => $sumaPesos > 0
                ? round($tareasCalificadas->sum(fn ($t) => $t['nota'] * $t['peso']) / $sumaPesos, 2)
                : null,
            'promedio_quizzes' => $quizzesCalificados->isNotEmpty() ? round($quizzesCalificados->avg('puntaje'), 2) : null,
        ];
    }

    /**
     * ERS RF-66: porcentaje de avance (0–100) del estudiante en el curso.
     */
    public function avance(User $estudiante, Curso $curso): int
    {
        $tareas = $curso->tareas()->visiblesParaEstudiante()->pluck('id');
        $quizzes = $curso->quizzes()->visiblesParaEstudiante()->pluck('id');
        $lecciones = $this->leccionesVisibles($curso);

        $total = $tareas->count() + $quizzes->count() + $lecciones->count();

        if ($total === 0) {
            return 0;
        }

        $completos = $estudiante->entregas()->whereIn('tarea_id', $tareas)->count()
            + $quizzes->intersect(ResultadoQuiz::where('estudiante_id', $estudiante->id)->whereIn('quiz_id', $quizzes)->distinct()->pluck('quiz_id'))->count()
            + LeccionCompletada::where('estudiante_id', $estudiante->id)->whereIn('leccion_id', $lecciones)->count();

        return (int) round(min($completos, $total) / $total * 100);
    }

    /**
     * ERS RF-67: estado de cada módulo visible según la condición que definió
     * el Profesor (Modulo::CONDICIONES):
     * - lecciones: todas sus lecciones completadas.
     * - actividades: además, sus tareas entregadas y sus quizzes rendidos.
     * - aprobacion: además, sus tareas y quizzes con nota igual o mayor a la mínima.
     *
     * @return array<int, array{completado: bool, hechos: int, total: int, condicion: string}>
     */
    public function estadoModulos(User $estudiante, Curso $curso): array
    {
        $modulos = $curso->modulos()->visiblesParaEstudiante()
            ->with(['lecciones' => fn ($q) => $q->publicadas()])
            ->get();

        $leccionesCompletadas = LeccionCompletada::where('estudiante_id', $estudiante->id)
            ->whereIn('leccion_id', $modulos->flatMap->lecciones->pluck('id'))
            ->pluck('leccion_id')
            ->flip();

        $tareas = $curso->tareas()->visiblesParaEstudiante()
            ->with(['entregas' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
            ->get()
            ->filter(fn ($tarea) => Gate::forUser($estudiante)->allows('ver', $tarea))
            ->groupBy('modulo_id');

        $quizzes = $curso->quizzes()->visiblesParaEstudiante()
            ->with(['resultados' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
            ->get()
            ->groupBy('modulo_id');

        $notaMinima = $this->calculadora->notaMinima();
        $estado = [];

        foreach ($modulos as $modulo) {
            $condicion = $modulo->condicion_completitud ?: 'actividades';
            $cumplidos = $modulo->lecciones->map(fn ($leccion) => isset($leccionesCompletadas[$leccion->id]));

            if ($condicion !== 'lecciones') {
                foreach ($tareas[$modulo->id] ?? [] as $tarea) {
                    $entrega = $tarea->entregas->first();
                    $cumplidos->push($condicion === 'aprobacion'
                        ? $entrega?->nota !== null && (float) $entrega->nota >= $notaMinima
                        : $entrega !== null);
                }

                foreach ($quizzes[$modulo->id] ?? [] as $quiz) {
                    $mejor = $quiz->resultados->max('puntaje');
                    $cumplidos->push($condicion === 'aprobacion'
                        ? $mejor !== null && (float) $mejor >= $notaMinima
                        : $quiz->resultados->isNotEmpty());
                }
            }

            $hechos = $cumplidos->filter()->count();

            $estado[$modulo->id] = [
                'completado' => $cumplidos->isNotEmpty() && $hechos === $cumplidos->count(),
                'hechos' => $hechos,
                'total' => $cumplidos->count(),
                'condicion' => Modulo::CONDICIONES[$condicion] ?? Modulo::CONDICIONES['actividades'],
            ];
        }

        return $estado;
    }

    /**
     * ERS RF-69 y RF-89: reporte de un curso para el profesor o el supervisor.
     */
    public function resumenCurso(Curso $curso): array
    {
        $estudiantes = $curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter();
        $tareas = $curso->tareas()->with('entregas')->get();
        $entregas = $tareas->flatMap->entregas->whereIn('estudiante_id', $estudiantes->pluck('id'));
        $calificadas = $entregas->where('estado', 'calificado');
        $totalEsperado = $tareas->count() * max($estudiantes->count(), 1);

        // Durante el periodo se evalúa el rendimiento a la fecha (rubros ya calificados).
        $notas = collect($this->calculadora->resumenDelCurso($curso, $estudiantes->pluck('id')));
        $rendimientos = $notas->pluck('rendimiento')->filter(fn ($nota) => $nota !== null);
        $avances = $estudiantes->mapWithKeys(fn (User $e) => [$e->id => $this->avance($e, $curso)]);

        // Bajo rendimiento (RF-89): rendimiento a la fecha bajo la nota mínima, o tareas vencidas
        // sin entregar. El avance total solo cuenta al cerrar el curso: a mitad del periodo
        // incluye contenido que todavía no toca y marcaría a casi todos.
        $tareasVencidas = $tareas->filter(fn ($tarea) => $tarea->esVisibleParaEstudiante() && $tarea->fecha_entrega->isPast());
        $vencidasSinEntregar = $estudiantes->mapWithKeys(fn (User $e) => [
            $e->id => $tareasVencidas->filter(fn ($tarea) => ! $tarea->entregas->contains('estudiante_id', $e->id))->count(),
        ]);

        $bajoRendimiento = $estudiantes
            ->filter(fn (User $e) => ($notas[$e->id]['rendimiento'] !== null && $notas[$e->id]['rendimiento'] < $this->calculadora->notaMinima())
                || $vencidasSinEntregar[$e->id] >= self::TAREAS_VENCIDAS_MAXIMAS
                || ($curso->estaFinalizado() && $avances[$e->id] < self::AVANCE_MINIMO))
            ->map(fn (User $e) => [
                'estudiante' => $e,
                'nota_final' => $notas[$e->id]['rendimiento'],
                'avance' => $avances[$e->id],
                'vencidas' => $vencidasSinEntregar[$e->id],
            ])
            ->values();

        return [
            'curso' => $curso,
            'inscritos' => $estudiantes->count(),
            // Promedio de tareas calificadas (práctica), se conserva por compatibilidad.
            'promedio' => $calificadas->isNotEmpty() ? round((float) $calificadas->avg('nota'), 2) : null,
            'tasa_entrega' => $totalEsperado > 0 ? round(($entregas->count() / $totalEsperado) * 100, 1) : 0.0,
            'entregas' => $entregas->count(),
            'por_calificar' => $entregas->where('estado', 'entregado')->count(),
            'progreso_promedio' => $avances->isNotEmpty() ? (int) round($avances->avg()) : 0,
            'promedio_final' => $rendimientos->isNotEmpty() ? round($rendimientos->avg(), 1) : null,
            'tasa_aprobacion' => $this->calculadora->tasaAprobacion($notas),
            'bajo_rendimiento' => $bajoRendimiento,
        ];
    }

    /**
     * Resumen para el panel del estudiante: progreso por curso matriculado,
     * próxima entrega y actividades pendientes más urgentes.
     */
    public function resumenDashboardEstudiante(User $estudiante): array
    {
        // El panel muestra los cursos en marcha; los archivados siguen disponibles en "Mis cursos".
        $cursos = Curso::whereHas('inscripcionesActivas', fn ($q) => $q->where('estudiante_id', $estudiante->id))
            ->visiblesParaEstudiantes()
            ->where('estado', '!=', 'archivado')
            ->with('modulos')
            ->get();

        $pendientes = collect();

        $filas = $cursos->map(function (Curso $curso) use ($estudiante, &$pendientes) {
            $tareas = $curso->tareas()
                ->visiblesParaEstudiante()
                ->with(['entregas' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
                ->get();

            $quizzes = $curso->quizzes()
                ->visiblesParaEstudiante()
                ->with(['resultados' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
                ->get();

            // "Próxima" entrega = la siguiente fecha límite futura sin entregar (no una ya vencida).
            $proximaTarea = $tareas
                ->filter(fn ($t) => $t->entregas->isEmpty() && $t->fecha_entrega->isFuture())
                ->sortBy('fecha_entrega')
                ->first();

            foreach ($tareas as $tarea) {
                if ($tarea->entregas->isEmpty()) {
                    $pendientes->push([
                        'tipo' => 'tarea',
                        'titulo' => $tarea->titulo,
                        'curso' => $curso,
                        'fecha' => $tarea->fecha_entrega,
                        'atrasado' => $tarea->estaVencida(),
                        'url' => route('cursos.tareas.show', [$curso, $tarea]),
                    ]);
                }
            }

            foreach ($quizzes as $quiz) {
                if ($quiz->resultados->isEmpty() && $quiz->estaAbierto()) {
                    $pendientes->push([
                        'tipo' => 'quiz',
                        'titulo' => $quiz->tema,
                        'curso' => $curso,
                        'fecha' => $quiz->fecha_limite,
                        'atrasado' => false,
                        'url' => route('cursos.quizzes.show', [$curso, $quiz]),
                    ]);
                }
            }

            return [
                'curso' => $curso,
                'progreso' => $this->avance($estudiante, $curso),
                'proxima_fecha' => $proximaTarea?->fecha_entrega,
            ];
        });

        return [
            'progreso_general' => $filas->isNotEmpty() ? (int) round($filas->avg('progreso')) : 0,
            'cursos' => $filas,
            'proximas_entregas' => $pendientes
                ->sortBy(fn ($p) => $p['fecha'] ?? Carbon::now()->addYears(10))
                ->take(5)
                ->values(),
            'total_pendientes' => $pendientes->count(),
            'proxima_entrega' => $pendientes
                ->filter(fn ($p) => $p['fecha'] !== null && $p['fecha']->isFuture())
                ->sortBy('fecha')
                ->first()['fecha'] ?? null,
        ];
    }

    /**
     * ERS RF-58: nota final ponderada por rubros (null sin esquema o sin notas).
     */
    public function notaFinalPorRubros(User $estudiante, Curso $curso): ?float
    {
        return $this->calculadora->notaFinalDe($estudiante, $curso);
    }

    /**
     * ERS RF-59: resumen de notas por rubro para la boleta y el reporte del estudiante.
     */
    public function resumenRubros(User $estudiante, Curso $curso): array
    {
        $esquema = $curso->esquemaEvaluacion()->with('rubros')->first();
        $rubros = $esquema?->rubros ?? collect();
        $notaMinima = $this->calculadora->notaMinima();

        $notas = app(NotasDeEvaluacion::class)
            ->resolver($rubros, collect([$estudiante->id]))[$estudiante->id] ?? [];

        $notaFinal = $this->calculadora->notaFinal($rubros, $notas);
        $condicion = $this->calculadora->condicion($rubros, $notas);

        return [
            'esquema' => $esquema,
            'rubros' => $rubros,
            'filas' => $rubros->map(fn ($rubro) => [
                'rubro' => $rubro,
                'nota' => $notas[$rubro->id] ?? null,
                'aprobado' => ($notas[$rubro->id] ?? null) !== null && $notas[$rubro->id] >= $notaMinima,
                'aporte' => ($notas[$rubro->id] ?? null) !== null ? round($notas[$rubro->id] * ((float) $rubro->peso / 100), 2) : null,
            ]),
            'nota_final' => $notaFinal,
            'rendimiento' => $this->calculadora->rendimiento($rubros, $notas),
            'condicion' => $condicion,
            'aprobado' => $condicion === 'aprobado',
        ];
    }

    /**
     * @return Collection<int, int> ids de lecciones publicadas en módulos visibles
     */
    protected function leccionesVisibles(Curso $curso): Collection
    {
        return Leccion::publicadas()
            ->whereHas('modulo', fn ($q) => $q->where('curso_id', $curso->id)->visiblesParaEstudiante())
            ->pluck('id');
    }
}
