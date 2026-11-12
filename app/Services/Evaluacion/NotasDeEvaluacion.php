<?php

namespace App\Services\Evaluacion;

use App\Models\Entrega;
use App\Models\NotaRubro;
use App\Models\ResultadoQuiz;
use App\Models\Tarea;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class NotasDeEvaluacion
{
    /**
     * Notas efectivas por estudiante y rubro. Una evaluación automática (promedio
     * ponderado por el peso de cada actividad, o un examen al 100 %) queda
     * pendiente hasta que todas sus actividades tengan nota, incluidos los ceros.
     * Una tarea vencida que el estudiante no entregó cuenta como 0, solo con su
     * peso dentro de la evaluación (si entrega tarde, espera a que se califique).
     * Las notas manuales se conservan al activar el cálculo automático.
     *
     * @return array<int, array<int, float|null>>
     */
    public function resolver(SupportCollection $rubros, SupportCollection $estudianteIds): array
    {
        $notas = NotaRubro::whereIn('rubro_id', $rubros->pluck('id'))
            ->whereIn('estudiante_id', $estudianteIds)->get()->groupBy('estudiante_id');
        $automaticos = new Collection($rubros->filter->esAutomatico()->all());
        $automaticos->loadMissing(['tareas', 'quizzes']);

        $entregas = $automaticos->isEmpty() ? collect() : Entrega::whereIn('tarea_id', $automaticos->flatMap->tareas->pluck('id')->unique())
            ->whereIn('estudiante_id', $estudianteIds)->get()
            ->keyBy(fn ($entrega) => $entrega->estudiante_id.'_'.$entrega->tarea_id);
        $resultados = $automaticos->isEmpty() ? collect() : ResultadoQuiz::whereIn('quiz_id', $automaticos->flatMap->quizzes->pluck('id')->unique())
            ->whereIn('estudiante_id', $estudianteIds)->get()
            ->groupBy(fn ($resultado) => $resultado->estudiante_id.'_'.$resultado->quiz_id)
            ->map(fn ($intentos) => $intentos->max('puntaje'));

        return $estudianteIds->mapWithKeys(function ($estudianteId) use ($notas, $automaticos, $entregas, $resultados) {
            $efectivas = ($notas[$estudianteId] ?? collect())->mapWithKeys(fn ($nota) => [
                $nota->rubro_id => $nota->nota !== null ? (float) $nota->nota : null,
            ])->all();

            foreach ($automaticos as $rubro) {
                $valores = $rubro->tareas->map(fn ($tarea) => ['nota' => $this->notaDeTarea($tarea, $entregas[$estudianteId.'_'.$tarea->id] ?? null), 'peso' => (float) $tarea->pivot->peso])
                    ->concat($rubro->quizzes->map(fn ($quiz) => ['nota' => $resultados[$estudianteId.'_'.$quiz->id] ?? null, 'peso' => (float) $quiz->pivot->peso]));
                $pesoTotal = $valores->sum('peso');
                // Con prácticas de campo, la nota espera a que el profesor prepare todas las de su tramo (PracticasEnEvaluaciones).
                $faltanPracticas = $rubro->tienePracticas() && $rubro->tareas->whereNotNull('practica')->count() < $rubro->cantidadPracticas();
                $efectivas[$rubro->id] = $faltanPracticas || $valores->isEmpty() || $pesoTotal <= 0 || $valores->contains(fn ($valor) => $valor['nota'] === null)
                    ? null : round($valores->sum(fn ($valor) => (float) $valor['nota'] * $valor['peso']) / $pesoTotal, 1);
            }

            return [$estudianteId => $efectivas];
        })->all();
    }

    /**
     * Nota de la tarea para la evaluación: la de su entrega calificada; 0 si
     * venció y el estudiante no entregó nada; null si aún está pendiente.
     */
    protected function notaDeTarea(Tarea $tarea, ?Entrega $entrega): ?float
    {
        if ($entrega) {
            return $entrega->estado === 'calificado' && $entrega->nota !== null ? (float) $entrega->nota : null;
        }

        return $tarea->esVisibleParaEstudiante() && $tarea->estaVencida() ? 0.0 : null;
    }
}
