<?php

namespace App\Services\Evaluacion;

use App\Enums\ModoCalculo;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\RubroEvaluacion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prácticas de campo dentro de las evaluaciones del curso.
 *
 * - El Supervisor fija cuánto valen en total las prácticas, como % dentro de
 *   las evaluaciones (esquemas_evaluacion.peso_practicas_campo, p. ej. 30).
 * - El profesor reparte ese total en tramos: cada evaluación recibe un rango
 *   de prácticas (Práctica desde..hasta) y un % de su nota, p. ej. Eva1 =
 *   Prácticas 1-4 (10 %), Eva2 = 5-8 (10 %), Eva3 = 9-12 (10 %); o todas en
 *   una sola evaluación (1-12, 30 %). Los tramos cubren todas las prácticas,
 *   sin solaparse, y suman exactamente el total del Supervisor.
 * - Dentro del tramo cada práctica vale partes iguales. Cada práctica es una
 *   tarea (tareas.practica) que se vincula a su evaluación por rubro_tarea con
 *   ese peso, así el cálculo de notas existente la incluye sin cambios. Las
 *   demás actividades de la evaluación suman 100 % menos el tramo.
 */
class PracticasEnEvaluaciones
{
    /**
     * Guarda el reparto del profesor.
     *
     * @param  array<int, array{desde: ?int, hasta: ?int, peso: ?float}>  $tramos  por id de evaluación; sin tramo = todo null
     *
     * @throws ValidationException
     */
    public function guardar(Curso $curso, array $tramos, User $profesor): void
    {
        $esquema = $curso->esquemaEvaluacion()->with(['rubros.tareas', 'rubros.quizzes'])->first();
        $total = $curso->totalPracticasCampo();
        $pesoTotal = (float) ($esquema?->peso_practicas_campo ?? 0);

        if ($esquema === null || $total === 0 || $pesoTotal <= 0) {
            throw ValidationException::withMessages(['tramos' => 'El Supervisor aún no definió cuánto valen las prácticas de campo en este curso.']);
        }

        $rubros = $esquema->rubros->keyBy('id');
        $asignadas = [];
        $errores = [];

        foreach ($tramos as $rubroId => $tramo) {
            if ($tramo['desde'] === null && $tramo['hasta'] === null && $tramo['peso'] === null) {
                continue;
            }

            $rubro = $rubros->get($rubroId) ?? abort(404);
            $campo = "tramos.{$rubroId}";

            if ($tramo['desde'] === null || $tramo['hasta'] === null || $tramo['peso'] === null) {
                $errores[$campo] = 'Completa desde, hasta y el % o deja la fila vacía.';

                continue;
            }

            if ($rubro->modo_calculo === ModoCalculo::Manual || $rubro->modo_calculo === ModoCalculo::Examen) {
                $errores[$campo] = "{$rubro->nombre} se califica como {$rubro->modo_calculo->etiqueta()}: cámbiala a Promedio de actividades para sumarle prácticas.";

                continue;
            }

            if ($tramo['desde'] < 1 || $tramo['hasta'] > $total || $tramo['desde'] > $tramo['hasta']) {
                $errores[$campo] = "Elige prácticas entre la 1 y la {$total}, con «desde» menor o igual que «hasta».";

                continue;
            }

            $otrasActividades = $rubro->tareas->whereNull('practica')->count() + $rubro->quizzes->count();

            if ($tramo['peso'] <= 0 || $tramo['peso'] > 100 || ($tramo['peso'] >= 100 && $otrasActividades > 0)) {
                $errores[$campo] = $tramo['peso'] >= 100 && $otrasActividades > 0
                    ? "{$rubro->nombre} tiene otras actividades: las prácticas deben valer menos de 100 %."
                    : 'Usa un % mayor que 0 y hasta 100.';

                continue;
            }

            foreach (range($tramo['desde'], $tramo['hasta']) as $numero) {
                if (isset($asignadas[$numero])) {
                    $errores[$campo] = "La práctica {$numero} ya está en {$rubros[$asignadas[$numero]]->nombre}. Los tramos no pueden solaparse.";
                }

                $asignadas[$numero] ??= $rubroId;
            }
        }

        if ($errores === []) {
            $faltantes = array_values(array_diff(range(1, $total), array_keys($asignadas)));
            $suma = round(array_sum(array_map(fn (array $tramo) => (float) ($tramo['peso'] ?? 0), $tramos)), 2);

            if ($faltantes !== []) {
                $errores['tramos'] = 'Todas las prácticas deben contar para alguna evaluación. Faltan: '.implode(', ', $faltantes).'.';
            } elseif (abs($suma - $pesoTotal) >= 0.005) {
                $errores['tramos'] = 'Los % suman '.$this->formato($suma).' % y el Supervisor definió '.$this->formato($pesoTotal).' % para las prácticas de campo.';
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        DB::transaction(function () use ($curso, $rubros, $tramos, $profesor) {
            $anterior = $rubros->map->only(['nombre', 'practica_desde', 'practica_hasta', 'peso_practicas'])->values()->all();

            foreach ($rubros as $rubro) {
                $tramo = $tramos[$rubro->id] ?? null;
                $conTramo = $tramo !== null && $tramo['peso'] !== null;
                $pesoNuevo = $conTramo ? round((float) $tramo['peso'], 2) : null;

                $this->reescalarActividades($rubro, (float) ($rubro->peso_practicas ?? 0), (float) ($pesoNuevo ?? 0));

                $rubro->update([
                    'practica_desde' => $conTramo ? $tramo['desde'] : null,
                    'practica_hasta' => $conTramo ? $tramo['hasta'] : null,
                    'peso_practicas' => $pesoNuevo,
                    'modo_calculo' => $conTramo && $rubro->modo_calculo === null ? ModoCalculo::Promedio : $rubro->modo_calculo,
                ]);
            }

            $this->sincronizar($curso);

            BitacoraAuditoria::registrarAccionAdmin(
                ejecutor: $profesor, tipoEvento: 'modificacion_esquema',
                descripcion: 'Prácticas de campo repartidas entre las evaluaciones',
                curso: $curso,
                valorAnterior: $anterior,
                valorNuevo: $rubros->map(fn (RubroEvaluacion $rubro) => $rubro->fresh()->only(['nombre', 'practica_desde', 'practica_hasta', 'peso_practicas']))->values()->all(),
            );
        });
    }

    /**
     * Vincula cada práctica (su tarea) a la evaluación de su tramo con su
     * parte igual del %; la última del tramo recibe el redondeo para que el
     * tramo sume exacto. Una práctica sin tramo no cuenta para ninguna.
     * Se llama al guardar el reparto y al crear la tarea de una práctica.
     */
    public function sincronizar(Curso $curso): void
    {
        $rubros = $curso->esquemaEvaluacion?->rubros()->get() ?? collect();
        $practicas = $curso->tareas()->whereNotNull('practica')->pluck('id', 'practica');

        if ($rubros->isEmpty() || $practicas->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($rubros, $practicas) {
            DB::table('rubro_tarea')->whereIn('rubro_id', $rubros->modelKeys())->whereIn('tarea_id', $practicas->values())->delete();

            $filas = $rubros->filter->tienePracticas()->flatMap(function (RubroEvaluacion $rubro) use ($practicas) {
                $pesos = $this->partesIguales((float) $rubro->peso_practicas, $rubro->cantidadPracticas());

                return collect(range($rubro->practica_desde, $rubro->practica_hasta))
                    ->filter(fn (int $numero) => $practicas->has($numero))
                    ->map(fn (int $numero) => [
                        'rubro_id' => $rubro->id,
                        'tarea_id' => $practicas[$numero],
                        'peso' => $pesos[$numero - $rubro->practica_desde],
                    ]);
            });

            if ($filas->isNotEmpty()) {
                DB::table('rubro_tarea')->insert($filas->values()->all());
            }
        });
    }

    /**
     * Reparto sugerido: las prácticas en bloques consecutivos y el total en
     * partes iguales entre las evaluaciones que pueden recibirlas (no
     * manuales ni de examen), en orden.
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @return array<int, array{desde: int, hasta: int, peso: float}>
     */
    public function repartoSugerido(Collection $rubros, int $totalPracticas, float $pesoTotal): array
    {
        $destino = $rubros->reject(fn (RubroEvaluacion $rubro) => in_array($rubro->modo_calculo, [ModoCalculo::Manual, ModoCalculo::Examen], true))
            ->take($totalPracticas)->values();

        if ($destino->isEmpty() || $totalPracticas === 0) {
            return [];
        }

        $pesos = $this->partesIguales($pesoTotal, $destino->count());
        $tramos = [];
        $desde = 1;

        foreach ($destino as $indice => $rubro) {
            $cantidad = intdiv($totalPracticas, $destino->count()) + ($indice < $totalPracticas % $destino->count() ? 1 : 0);
            $tramos[$rubro->id] = ['desde' => $desde, 'hasta' => $desde + $cantidad - 1, 'peso' => $pesos[$indice]];
            $desde += $cantidad;
        }

        return $tramos;
    }

    /**
     * Las demás actividades de la evaluación conservan sus proporciones y
     * pasan a sumar 100 % menos el tramo de prácticas.
     */
    protected function reescalarActividades(RubroEvaluacion $rubro, float $pesoAnterior, float $pesoNuevo): void
    {
        $actividades = $rubro->tareas->whereNull('practica')->map(fn ($tarea) => ['tabla' => 'rubro_tarea', 'columna' => 'tarea_id', 'id' => $tarea->id, 'peso' => (float) $tarea->pivot->peso])
            ->concat($rubro->quizzes->map(fn ($quiz) => ['tabla' => 'rubro_quiz', 'columna' => 'quiz_id', 'id' => $quiz->id, 'peso' => (float) $quiz->pivot->peso]))
            ->values();
        $sumaAnterior = $actividades->sum('peso');

        if (abs($pesoAnterior - $pesoNuevo) < 0.005 || $actividades->isEmpty() || $sumaAnterior <= 0) {
            return;
        }

        $objetivo = round(100 - $pesoNuevo, 2);
        $acumulado = 0.0;

        foreach ($actividades as $indice => $actividad) {
            $peso = $indice === $actividades->count() - 1
                ? round($objetivo - $acumulado, 2)
                : round($actividad['peso'] * $objetivo / $sumaAnterior, 2);
            $acumulado += $peso;

            DB::table($actividad['tabla'])->where('rubro_id', $rubro->id)->where($actividad['columna'], $actividad['id'])->update(['peso' => $peso]);
        }
    }

    /**
     * @return list<float> $partes valores que suman exactamente $total
     */
    protected function partesIguales(float $total, int $partes): array
    {
        $base = floor($total * 100 / $partes) / 100;
        $pesos = array_fill(0, $partes, $base);
        $pesos[$partes - 1] = round($total - $base * ($partes - 1), 2);

        return $pesos;
    }

    protected function formato(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');
    }
}
