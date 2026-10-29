<?php

namespace App\Services\Evaluacion;

use App\Models\BancoPregunta;
use App\Models\Curso;
use App\Models\Quiz;
use App\Models\ResultadoQuiz;

/**
 * Qué tanto fallan los estudiantes cada pregunta del banco (solo lectura).
 *
 * Cada intento enviado se guarda en resultados_quiz.respuestas como un
 * arreglo por POSICIÓN de la pregunta dentro de quizzes.preguntas (la
 * posición no cambia mientras el quiz tiene intentos: su estructura queda
 * bloqueada). Por eso se suma, para cada pregunta del banco, la respuesta
 * en esa posición de todos los intentos de todos los quizzes donde se copió.
 *
 * Una copia se reconoce por su id de origen (banco_pregunta_id). Las copias
 * anteriores a ese dato solo se cruzan si es seguro: traen su tema de origen
 * (banco_tema), en el banco hay UNA sola pregunta con ese tema y enunciado, y
 * opciones, tipo y correctas son idénticas. Las copias editadas en el quiz no
 * cuentan: ya no son la misma pregunta.
 *
 * Todo se calcula en lote: una consulta para el banco, una para los quizzes
 * y los intentos se recorren por bloques.
 */
class EstadisticaFallosBanco
{
    /** Con menos respuestas se muestra "Sin datos" en lugar del porcentaje. */
    public const MINIMO_RESPUESTAS = 3;

    /**
     * @return array<int, array{respuestas: int, incorrectas: int, porcentaje: float|null}> id de la pregunta del banco => estadística
     */
    public function paraCurso(Curso $curso): array
    {
        $banco = $curso->bancoPreguntasCurso()->get(['id', 'tema', 'pregunta', 'tipo', 'opciones', 'respuestas_correctas']);
        $estadistica = $banco->mapWithKeys(fn (BancoPregunta $p) => [$p->id => ['respuestas' => 0, 'incorrectas' => 0]])->all();

        if ($estadistica === []) {
            return [];
        }

        $origenes = $this->origenesPorQuiz($curso, $banco->keyBy('id')->all());

        if ($origenes !== []) {
            $intentos = ResultadoQuiz::query()->whereIn('quiz_id', array_keys($origenes))->select(['id', 'quiz_id', 'respuestas']);

            foreach ($intentos->lazyById(500) as $intento) {
                foreach ($origenes[$intento->quiz_id] as $indice => [$bancoId, $pregunta]) {
                    $respuesta = $intento->respuestas[$indice] ?? null;

                    if ($respuesta === null) {
                        continue;
                    }

                    $estadistica[$bancoId]['respuestas']++;

                    if (! Quiz::respuestaEsCorrecta($pregunta, $respuesta)) {
                        $estadistica[$bancoId]['incorrectas']++;
                    }
                }
            }
        }

        return array_map(fn (array $e) => [
            ...$e,
            'porcentaje' => $e['respuestas'] >= self::MINIMO_RESPUESTAS ? round($e['incorrectas'] / $e['respuestas'] * 100, 1) : null,
        ], $estadistica);
    }

    /**
     * "40 % de fallos (5 respuestas)" o "Sin datos" (menos de MINIMO_RESPUESTAS).
     *
     * @param  array{respuestas?: int, porcentaje?: float|null}|null  $estadistica
     */
    public static function texto(?array $estadistica): string
    {
        if (($estadistica['porcentaje'] ?? null) === null) {
            return 'Sin datos';
        }

        $porcentaje = rtrim(rtrim(number_format($estadistica['porcentaje'], 1, '.', ''), '0'), '.');

        return "{$porcentaje} % de fallos ({$estadistica['respuestas']} respuestas)";
    }

    /**
     * Para cada quiz con copias reconocibles: posición => [id del banco, pregunta del quiz].
     *
     * @param  array<int, BancoPregunta>  $banco
     * @return array<int, array<int, array{0: int, 1: array<string, mixed>}>>
     */
    protected function origenesPorQuiz(Curso $curso, array $banco): array
    {
        $porClave = [];
        foreach ($banco as $item) {
            $porClave[BancoPregunta::claveDuplicado($item->tema, $item->pregunta)][] = $item->id;
        }

        $origenes = [];

        foreach ($curso->quizzes()->get(['id', 'preguntas']) as $quiz) {
            foreach ($quiz->preguntas ?? [] as $indice => $pregunta) {
                if (! is_array($pregunta) || ($pregunta['editada'] ?? false)) {
                    continue;
                }

                $bancoId = $this->origenDe($pregunta, $banco, $porClave);

                if ($bancoId !== null) {
                    $origenes[$quiz->id][$indice] = [$bancoId, $pregunta];
                }
            }
        }

        return $origenes;
    }

    /**
     * @param  array<string, mixed>  $pregunta
     * @param  array<int, BancoPregunta>  $banco
     * @param  array<string, list<int>>  $porClave
     */
    protected function origenDe(array $pregunta, array $banco, array $porClave): ?int
    {
        if (isset($pregunta['banco_pregunta_id'])) {
            $id = (int) $pregunta['banco_pregunta_id'];

            return isset($banco[$id]) ? $id : null;
        }

        if (! filled($pregunta['banco_tema'] ?? null)) {
            return null;
        }

        $candidatas = $porClave[BancoPregunta::claveDuplicado((string) $pregunta['banco_tema'], (string) ($pregunta['enunciado'] ?? ''))] ?? [];

        if (count($candidatas) !== 1) {
            return null;
        }

        $original = $banco[$candidatas[0]]->aPreguntaDeQuiz();
        $mismoContenido = ($pregunta['tipo'] ?? 'opcion_unica') === $original['tipo']
            && array_values((array) ($pregunta['opciones'] ?? [])) === $original['opciones']
            && Quiz::correctasDe($pregunta) === Quiz::correctasDe($original);

        return $mismoContenido ? $candidatas[0] : null;
    }
}
