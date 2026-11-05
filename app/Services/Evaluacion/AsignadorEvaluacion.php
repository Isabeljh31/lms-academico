<?php

namespace App\Services\Evaluacion;

use App\Enums\ModoCalculo;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Quiz;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Asigna una actividad recién creada (asistente «¿Qué quieres crear?» del
 * Profesor) a una evaluación del curso con el mecanismo que ya existe:
 * rubro_tarea / rubro_quiz con su peso y el modo de cálculo del rubro.
 *
 * - Una actividad cuenta para una sola evaluación (índice único de los pivotes).
 * - Los pesos dentro de la evaluación siempre suman 100 %: si ya tenía
 *   actividades en partes iguales se vuelve a repartir en partes iguales; si
 *   el profesor los había ajustado, se conservan sus proporciones y la nueva
 *   recibe una parte igual.
 * - Las evaluaciones que el profesor califica a mano (modo manual) no se
 *   tocan: cambiarlas a automáticas ocultaría las notas ya escritas.
 * - Una evaluación sin actividades queda en modo Examen si se le asigna un
 *   examen y en Promedio en cualquier otro caso.
 */
class AsignadorEvaluacion
{
    /**
     * Evaluaciones del curso a las que se puede asignar una actividad nueva.
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @return Collection<int, RubroEvaluacion>
     */
    public function disponibles(Collection $rubros): Collection
    {
        return $rubros->reject(fn (RubroEvaluacion $rubro) => $rubro->modo_calculo === ModoCalculo::Manual)->values();
    }

    /**
     * La primera evaluación desde la semana de la actividad; si todas son
     * anteriores, la última. Sin semana (prácticas de campo), la primera.
     *
     * @param  Collection<int, RubroEvaluacion>  $disponibles
     */
    public function sugerida(Collection $disponibles, ?int $semana): ?RubroEvaluacion
    {
        if ($semana === null) {
            return $disponibles->first();
        }

        return $disponibles->first(fn (RubroEvaluacion $rubro) => $rubro->semana === null || $rubro->semana >= $semana)
            ?? $disponibles->last();
    }

    /** La actividad es de una semana posterior a la de la evaluación (aviso, no bloquea). */
    public function esPosterior(RubroEvaluacion $rubro, ?int $semana): bool
    {
        return $semana !== null && $rubro->semana !== null && $semana > $rubro->semana;
    }

    /**
     * Vincula la actividad y reparte los pesos. Devuelve el reparto final
     * («Título» => peso) para el mensaje de confirmación.
     *
     * @return array<string, float>
     */
    public function asignar(Curso $curso, RubroEvaluacion $rubro, Tarea|Quiz $actividad, bool $esExamen, User $profesor): array
    {
        abort_if($rubro->modo_calculo === ModoCalculo::Manual, 422, 'Esta evaluación se califica a mano.');
        abort_if($actividad->rubrosEvaluacion()->exists(), 422, 'La actividad ya cuenta para una evaluación.');

        return DB::transaction(function () use ($curso, $rubro, $actividad, $esExamen, $profesor) {
            $rubro->load(['tareas', 'quizzes']);
            $anteriores = $rubro->actividadesVinculadas();
            $clave = ($actividad instanceof Tarea ? 'tarea-' : 'quiz-').$actividad->id;
            $pesos = $this->repartir($anteriores->mapWithKeys(fn (array $vinculada) => [$vinculada['tipo'].'-'.$vinculada['id'] => $vinculada['peso']])->all(), $clave);
            $modo = $anteriores->isEmpty() && $esExamen ? ModoCalculo::Examen : ModoCalculo::Promedio;
            $modoAnterior = $rubro->modo_calculo?->value;

            $rubro->update(['modo_calculo' => $modo]);
            $rubro->tareas()->sync($this->pivotes($pesos, 'tarea'));
            $rubro->quizzes()->sync($this->pivotes($pesos, 'quiz'));

            BitacoraAuditoria::registrarAccionAdmin(
                ejecutor: $profesor, tipoEvento: 'modificacion_esquema',
                descripcion: 'Actividad nueva asignada a '.$rubro->nombre,
                curso: $curso,
                valorAnterior: ['modo' => $modoAnterior, 'actividades' => $anteriores->all()],
                valorNuevo: ['modo' => $modo->value, 'pesos' => $pesos],
            );

            $titulos = $anteriores->mapWithKeys(fn (array $vinculada) => [$vinculada['tipo'].'-'.$vinculada['id'] => $vinculada['titulo']])
                ->put($clave, $actividad instanceof Tarea ? $actividad->titulo : $actividad->tema);

            return collect($pesos)->mapWithKeys(fn (float $peso, string $id) => [$titulos[$id] => $peso])->all();
        });
    }

    /**
     * @param  array<string, float>  $anteriores  clave => peso (suman 100)
     * @return array<string, float>
     */
    protected function repartir(array $anteriores, string $nueva): array
    {
        $claves = [...array_keys($anteriores), $nueva];

        if ($anteriores === [] || count(array_unique(array_map('strval', $anteriores))) === 1) {
            return $this->repartoIgual($claves);
        }

        // El profesor había ajustado los pesos: se conservan sus proporciones.
        $parteNueva = floor(10000 / count($claves)) / 100;
        $pesos = array_map(fn (float $peso) => round($peso * (100 - $parteNueva) / 100, 2), $anteriores);
        $pesos[$nueva] = round(100 - array_sum($pesos), 2);

        return $pesos;
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, float>
     */
    protected function repartoIgual(array $claves): array
    {
        $base = floor(10000 / count($claves)) / 100;
        $pesos = array_fill_keys($claves, $base);
        $pesos[end($claves)] = round(100 - $base * (count($claves) - 1), 2);

        return $pesos;
    }

    /**
     * @param  array<string, float>  $pesos
     * @return array<int, array{peso: float}>
     */
    protected function pivotes(array $pesos, string $tipo): array
    {
        return collect($pesos)->filter(fn ($peso, string $clave) => str_starts_with($clave, $tipo.'-'))
            ->mapWithKeys(fn (float $peso, string $clave) => [(int) substr($clave, strlen($tipo) + 1) => ['peso' => $peso]])
            ->all();
    }
}
