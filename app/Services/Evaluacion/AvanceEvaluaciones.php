<?php

namespace App\Services\Evaluacion;

use App\Models\Curso;
use App\Models\Entrega;
use App\Models\NotaRubro;
use App\Models\ResultadoQuiz;
use App\Models\RubroEvaluacion;
use Illuminate\Support\Collection;

/**
 * Estado de cada evaluación del curso para el Profesor (pantalla Actividades):
 *
 * - falta_definir: todavía no tiene actividades que cuenten (o sus pesos no suman 100 %).
 * - en_proceso: faltan notas; lo pendiente no se cuenta como 0.
 * - lista: todos los alumnos matriculados tienen nota.
 *
 * Solo cuenta notas (no las calcula): las evaluaciones automáticas suman una
 * nota por alumno y actividad vinculada (entrega calificada o algún intento
 * del quiz); las manuales, una nota registrada por alumno. Hace como máximo
 * cuatro consultas, sin importar cuántas evaluaciones o actividades haya.
 */
class AvanceEvaluaciones
{
    /**
     * @param  Collection<int, RubroEvaluacion>  $rubros  con tareas y quizzes cargados
     * @return array<int, array{estado: string, faltan: int, total: int}> rubro_id => avance
     */
    public function deCurso(Curso $curso, Collection $rubros): array
    {
        if ($rubros->isEmpty()) {
            return [];
        }

        $estudianteIds = $curso->inscripciones()->where('estado', 'activo')->pluck('estudiante_id');
        $automaticos = $rubros->filter(fn (RubroEvaluacion $rubro) => $rubro->esAutomatico());
        $manuales = $rubros->filter(fn (RubroEvaluacion $rubro) => $rubro->modo_calculo !== null && ! $rubro->esAutomatico());
        $tareaIds = $automaticos->flatMap->tareas->pluck('id')->unique();
        $quizIds = $automaticos->flatMap->quizzes->pluck('id')->unique();
        $hayAlumnos = $estudianteIds->isNotEmpty();

        $notasPorTarea = $hayAlumnos && $tareaIds->isNotEmpty()
            ? Entrega::whereIn('tarea_id', $tareaIds)->whereIn('estudiante_id', $estudianteIds)
                ->where('estado', 'calificado')->whereNotNull('nota')
                ->selectRaw('tarea_id, count(distinct estudiante_id) as total')->groupBy('tarea_id')
                ->pluck('total', 'tarea_id')
            : collect();
        $notasPorQuiz = $hayAlumnos && $quizIds->isNotEmpty()
            ? ResultadoQuiz::whereIn('quiz_id', $quizIds)->whereIn('estudiante_id', $estudianteIds)
                ->selectRaw('quiz_id, count(distinct estudiante_id) as total')->groupBy('quiz_id')
                ->pluck('total', 'quiz_id')
            : collect();
        $notasPorRubro = $hayAlumnos && $manuales->isNotEmpty()
            ? NotaRubro::whereIn('rubro_id', $manuales->modelKeys())->whereIn('estudiante_id', $estudianteIds)
                ->whereNotNull('nota')
                ->selectRaw('rubro_id, count(*) as total')->groupBy('rubro_id')
                ->pluck('total', 'rubro_id')
            : collect();

        $alumnos = $estudianteIds->count();

        return $rubros->mapWithKeys(function (RubroEvaluacion $rubro) use ($alumnos, $notasPorTarea, $notasPorQuiz, $notasPorRubro) {
            if ($rubro->estadoCalculo() !== 'lista') {
                return [$rubro->id => ['estado' => 'falta_definir', 'faltan' => 0, 'total' => 0]];
            }

            [$total, $conNota] = $rubro->esAutomatico()
                ? [
                    $alumnos * ($rubro->tareas->count() + $rubro->quizzes->count()),
                    $rubro->tareas->sum(fn ($tarea) => (int) ($notasPorTarea[$tarea->id] ?? 0))
                        + $rubro->quizzes->sum(fn ($quiz) => (int) ($notasPorQuiz[$quiz->id] ?? 0)),
                ]
                : [$alumnos, (int) ($notasPorRubro[$rubro->id] ?? 0)];

            $faltan = max(0, $total - $conNota);

            return [$rubro->id => [
                'estado' => $total > 0 && $faltan === 0 ? 'lista' : 'en_proceso',
                'faltan' => $faltan,
                'total' => $total,
            ]];
        })->all();
    }
}
