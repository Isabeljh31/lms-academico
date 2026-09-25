<?php

namespace App\Services\Reportes;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Combina los Módulos 2, 3 y 4 (cursos, tareas/entregas, quizzes) para
 * armar el progreso académico usado tanto por el dashboard (Módulo 9,
 * gráficas) como por la exportación en PDF. No crea tablas nuevas.
 */
class ProgresoAcademicoService
{
    /**
     * Progreso de un estudiante en un curso: cada tarea/quiz con su nota
     * (si existe), y el promedio ponderado por `peso_nota` en las tareas.
     */
    public function progresoEstudiante(User $estudiante, Curso $curso): array
    {
        $tareas = $curso->tareas()
            ->where('visible', true)
            ->with(['entregas' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
            ->get()
            ->map(function ($tarea) {
                $entrega = $tarea->entregas->first();

                return [
                    'titulo' => $tarea->titulo,
                    'peso' => (float) $tarea->peso_nota,
                    'nota' => $entrega?->nota !== null ? (float) $entrega->nota : null,
                    'estado' => $entrega
                        ? $entrega->estado
                        : ($tarea->estaVencida() ? 'atrasado' : 'pendiente'),
                ];
            });

        $quizzes = $curso->quizzes()
            ->where('visible', true)
            ->with(['resultados' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
            ->get()
            ->map(fn ($quiz) => [
                'tema' => $quiz->tema,
                'puntaje' => $quiz->resultados->first()?->puntaje !== null ? (float) $quiz->resultados->first()->puntaje : null,
            ]);

        $tareasCalificadas = $tareas->filter(fn ($t) => $t['nota'] !== null);
        $sumaPesos = $tareasCalificadas->sum('peso');
        $promedioTareas = $sumaPesos > 0
            ? round($tareasCalificadas->sum(fn ($t) => $t['nota'] * $t['peso']) / $sumaPesos, 2)
            : null;

        $quizzesCalificados = $quizzes->filter(fn ($q) => $q['puntaje'] !== null);
        $promedioQuizzes = $quizzesCalificados->isNotEmpty() ? round($quizzesCalificados->avg('puntaje'), 2) : null;

        return [
            'curso' => $curso,
            'tareas' => $tareas,
            'quizzes' => $quizzes,
            'promedio_tareas' => $promedioTareas,
            'promedio_quizzes' => $promedioQuizzes,
        ];
    }

    /**
     * Resumen de un curso para el profesor/administrador: promedio general
     * y tasa de entrega entre todos los inscritos.
     */
    public function resumenCurso(Curso $curso): array
    {
        $inscritos = $curso->inscripciones;
        $tareas = $curso->tareas()->with('entregas')->get();
        $entregas = $tareas->flatMap->entregas;
        $calificadas = $entregas->where('estado', 'calificado');

        $totalEsperado = $tareas->count() * max($inscritos->count(), 1);

        return [
            'curso' => $curso,
            'promedio' => $calificadas->isNotEmpty() ? round((float) $calificadas->avg('nota'), 2) : null,
            'tasa_entrega' => $totalEsperado > 0 ? round(($entregas->count() / $totalEsperado) * 100, 1) : 0.0,
            'inscritos' => $inscritos->count(),
        ];
    }

    /**
     * Resumen para el dashboard principal del estudiante (rediseño visual
     * del rol estudiante): progreso por curso inscrito, próxima entrega
     * por curso, y las entregas/quizzes más urgentes de todos sus cursos
     * juntos. No modifica nada de lo ya construido en los Módulos 2-4-9;
     * es una lectura adicional para la vista.
     */
    public function resumenDashboardEstudiante(User $estudiante): array
    {
        $cursos = Curso::whereIn('id', $estudiante->inscripciones()->pluck('curso_id'))
            ->with('modulos')
            ->get();

        $pendientes = collect();

        $filas = $cursos->map(function (Curso $curso) use ($estudiante, &$pendientes) {
            $tareas = $curso->tareas()
                ->where('visible', true)
                ->with(['entregas' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
                ->get();

            $quizzes = $curso->quizzes()
                ->where('visible', true)
                ->with(['resultados' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
                ->get();

            $totalItems = $tareas->count() + $quizzes->count();
            $completos = $tareas->filter(fn ($t) => $t->entregas->isNotEmpty())->count()
                + $quizzes->filter(fn ($q) => $q->resultados->isNotEmpty())->count();

            $proximaTarea = $tareas
                ->filter(fn ($t) => $t->entregas->isEmpty())
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
                if ($quiz->resultados->isEmpty()) {
                    $pendientes->push([
                        'tipo' => 'quiz',
                        'titulo' => $quiz->tema,
                        'curso' => $curso,
                        'fecha' => null,
                        'atrasado' => false,
                        'url' => route('cursos.quizzes.show', [$curso, $quiz]),
                    ]);
                }
            }

            return [
                'curso' => $curso,
                'progreso' => $totalItems > 0 ? (int) round(($completos / $totalItems) * 100) : 0,
                'proxima_fecha' => $proximaTarea?->fecha_entrega,
            ];
        });

        $progresoGeneral = $filas->isNotEmpty() ? (int) round($filas->avg('progreso')) : 0;

        $proximasEntregas = $pendientes
            ->sortBy(fn ($p) => $p['fecha'] ?? Carbon::now()->addYears(10))
            ->take(5)
            ->values();

        return [
            'progreso_general' => $progresoGeneral,
            'cursos' => $filas,
            'proximas_entregas' => $proximasEntregas,
        ];
    }
}
