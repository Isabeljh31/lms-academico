<?php

namespace App\Models;

use App\Models\Concerns\TieneEstadoPublicacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * ERS RF-44 a RF-46 y RF-50 a RF-53: Quiz de práctica.
 *
 * Cada pregunta (JSON en `preguntas`):
 *   enunciado, tipo (opcion_unica | opcion_multiple | verdadero_falso),
 *   opciones (array de textos), respuesta_correcta (índice, para opción
 *   única y V/F), respuestas_correctas (índices, para opción múltiple),
 *   puntaje (por defecto 1) y retroalimentacion (opcional).
 *
 * `configuracion` (RF-45): tiempo_limite_min, intentos, aleatorizar,
 * mostrar_retroalimentacion.
 *
 * Las notas de quizzes son de práctica y nunca entran en la Nota Final
 * ponderada del Esquema de Evaluación (RF-48).
 */
class Quiz extends Model
{
    use HasFactory, TieneEstadoPublicacion;

    protected $table = 'quizzes';

    /** @var array<string, string> */
    public const TIPOS_PREGUNTA = [
        'opcion_unica' => 'Opción única',
        'opcion_multiple' => 'Opción múltiple',
        'verdadero_falso' => 'Verdadero / Falso',
    ];

    // Ver Tarea::$attributes: mismo espejo del default(true) de la migración.
    protected $attributes = [
        'visible' => true,
        'estado_contenido' => 'publicado',
    ];

    protected $fillable = [
        'curso_id',
        'modulo_id',
        'tema',
        'preguntas',
        'configuracion',
        'fecha_inicio',
        'fecha_limite',
        'visible',
        'estado_contenido',
        'publicar_en',
    ];

    protected function casts(): array
    {
        return [
            'preguntas' => 'array', // Deserialización nativa de JSONB
            'configuracion' => 'array',
            'fecha_inicio' => 'datetime',
            'fecha_limite' => 'datetime',
            'publicar_en' => 'datetime',
            'visible' => 'boolean',
        ];
    }

    protected function dependeDeVisibilidadYModulo(): bool
    {
        return true;
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoQuiz::class, 'quiz_id');
    }

    // ==========================================
    // CONFIGURACIÓN (RF-45)
    // ==========================================

    public function intentosPermitidos(): int
    {
        return max(1, (int) ($this->configuracion['intentos'] ?? 1));
    }

    public function tiempoLimiteMinutos(): ?int
    {
        $minutos = (int) ($this->configuracion['tiempo_limite_min'] ?? 0);

        return $minutos > 0 ? $minutos : null;
    }

    public function aleatoriza(): bool
    {
        return (bool) ($this->configuracion['aleatorizar'] ?? false);
    }

    public function muestraRetroalimentacion(): bool
    {
        return (bool) ($this->configuracion['mostrar_retroalimentacion'] ?? true);
    }

    public function estaAbierto(): bool
    {
        return ($this->fecha_inicio === null || $this->fecha_inicio->lte(now()))
            && ($this->fecha_limite === null || $this->fecha_limite->gte(now()));
    }

    // ==========================================
    // RESULTADOS E INTENTOS
    // ==========================================

    /** Mejor intento del estudiante (el que cuenta como nota del quiz). */
    public function resultadoDe(User $estudiante): ?ResultadoQuiz
    {
        $resultados = $this->relationLoaded('resultados')
            ? $this->resultados->where('estudiante_id', $estudiante->id)
            : $this->resultados()->where('estudiante_id', $estudiante->id)->get();

        return $resultados->sortByDesc(fn (ResultadoQuiz $r) => [(float) $r->puntaje, $r->intento])->first();
    }

    public function intentosUsados(User $estudiante): int
    {
        return $this->relationLoaded('resultados')
            ? $this->resultados->where('estudiante_id', $estudiante->id)->count()
            : $this->resultados()->where('estudiante_id', $estudiante->id)->count();
    }

    public function quedanIntentos(User $estudiante): bool
    {
        return $this->intentosUsados($estudiante) < $this->intentosPermitidos();
    }

    // ==========================================
    // CORRECCIÓN (RF-46)
    // ==========================================

    /**
     * Índices de las opciones correctas de una pregunta.
     *
     * @param  array<string, mixed>  $pregunta
     * @return array<int, int>
     */
    public static function correctasDe(array $pregunta): array
    {
        if (($pregunta['tipo'] ?? 'opcion_unica') === 'opcion_multiple') {
            $correctas = array_map('intval', (array) ($pregunta['respuestas_correctas'] ?? []));
            sort($correctas);

            return array_values(array_unique($correctas));
        }

        return [(int) ($pregunta['respuesta_correcta'] ?? 0)];
    }

    /**
     * @param  array<string, mixed>  $pregunta
     */
    public static function respuestaEsCorrecta(array $pregunta, mixed $respuesta): bool
    {
        if ($respuesta === null || $respuesta === '' || $respuesta === []) {
            return false;
        }

        // Comparación por valor: los índices elegidos llegan como string
        // desde el navegador y pueden estar guardados como int.
        $elegidas = array_map('intval', (array) $respuesta);
        sort($elegidas);

        return array_values(array_unique($elegidas)) === self::correctasDe($pregunta);
    }

    /**
     * @param  array<string, mixed>  $pregunta
     */
    public static function puntajeDe(array $pregunta): float
    {
        $puntaje = (float) ($pregunta['puntaje'] ?? 1);

        return $puntaje > 0 ? $puntaje : 1.0;
    }

    /**
     * Puntaje en escala 0–20, ponderado por el puntaje de cada pregunta.
     *
     * @param  array<int, int|string|array<int, int|string>|null>  $respuestas
     */
    public function calcularPuntaje(array $respuestas): float
    {
        $preguntas = $this->preguntas ?? [];

        if (count($preguntas) === 0) {
            return 0.0;
        }

        $total = 0.0;
        $obtenido = 0.0;

        foreach ($preguntas as $indice => $pregunta) {
            $valor = self::puntajeDe($pregunta);
            $total += $valor;

            if (self::respuestaEsCorrecta($pregunta, $respuestas[$indice] ?? null)) {
                $obtenido += $valor;
            }
        }

        return round(($obtenido / $total) * 20, 2);
    }

    /**
     * ERS RF-51: cuántos estudiantes distintos tienen intentos registrados.
     */
    public function estudiantesConIntentos(): int
    {
        return $this->resultados()->distinct('estudiante_id')->count('estudiante_id');
    }

    /**
     * ERS RF-52: recalcula el puntaje de todos los intentos con las
     * preguntas actuales. Devuelve solo los intentos cuyo puntaje cambió.
     *
     * @return Collection<int, array{resultado: ResultadoQuiz, anterior: float, nuevo: float}>
     */
    public function recalcularResultados(): Collection
    {
        return $this->resultados()->with('estudiante')->get()
            ->map(function (ResultadoQuiz $resultado) {
                $anterior = (float) $resultado->puntaje;
                $nuevo = $this->calcularPuntaje($resultado->respuestas ?? []);

                if (abs($anterior - $nuevo) < 0.001) {
                    return null;
                }

                $resultado->update(['puntaje' => $nuevo]);

                return ['resultado' => $resultado, 'anterior' => $anterior, 'nuevo' => $nuevo];
            })
            ->filter()
            ->values();
    }

    /**
     * Igual criterio que Tarea::puedeEliminarse(): con resultados de por
     * medio, borrar se llevaría esas notas en cascada, así que la única
     * opción es ocultarlo.
     */
    public function puedeEliminarse(): bool
    {
        return $this->relationLoaded('resultados')
            ? $this->resultados->isEmpty()
            : $this->resultados()->doesntExist();
    }

    /**
     * Estadística por pregunta para el profesor: qué preguntas tuvieron más
     * errores. Ordenado por tasa de error descendente. Considera el último
     * intento de cada estudiante.
     *
     * @return array<int, array{indice: int, enunciado: string, total_respuestas: int, correctas: int, incorrectas: int, tasa_error: float, distribucion: array<int, array{opcion: string, elegida_por: int, es_correcta: bool}>}>
     */
    public function estadisticasPorPregunta(): array
    {
        $resultados = ($this->relationLoaded('resultados') ? $this->resultados : $this->resultados()->get())
            ->sortByDesc('intento')
            ->unique('estudiante_id');
        $totalRespuestas = $resultados->count();

        return collect($this->preguntas ?? [])
            ->map(function (array $pregunta, int $indice) use ($resultados, $totalRespuestas) {
                $respuestas = $resultados->map(fn ($r) => $r->respuestas[$indice] ?? null)->filter(fn ($v) => $v !== null);
                $correctas = $respuestas->filter(fn ($v) => self::respuestaEsCorrecta($pregunta, $v))->count();
                $indicesCorrectos = self::correctasDe($pregunta);

                $distribucion = collect($pregunta['opciones'] ?? [])
                    ->map(fn (string $opcion, int $indiceOpcion) => [
                        'opcion' => $opcion,
                        'elegida_por' => $respuestas->filter(fn ($v) => in_array($indiceOpcion, array_map('intval', (array) $v), true))->count(),
                        'es_correcta' => in_array($indiceOpcion, $indicesCorrectos, true),
                    ])
                    ->values()
                    ->all();

                return [
                    'indice' => $indice,
                    'enunciado' => $pregunta['enunciado'] ?? '',
                    'total_respuestas' => $totalRespuestas,
                    'correctas' => $correctas,
                    'incorrectas' => $totalRespuestas - $correctas,
                    'tasa_error' => $totalRespuestas > 0 ? round((($totalRespuestas - $correctas) / $totalRespuestas) * 100, 1) : 0.0,
                    'distribucion' => $distribucion,
                ];
            })
            ->sortByDesc('tasa_error')
            ->values()
            ->all();
    }
}
