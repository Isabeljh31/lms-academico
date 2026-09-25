<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasFactory;

    protected $table = 'quizzes';

    // Ver Tarea::$attributes: mismo espejo del default(true) de la migración.
    protected $attributes = [
        'visible' => true,
    ];

    protected $fillable = [
        'curso_id',
        'modulo_id',
        'tema',
        'preguntas',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'preguntas' => 'array', // Deserialización nativa de JSONB
            'visible' => 'boolean',
        ];
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

    public function resultadoDe(User $estudiante): ?ResultadoQuiz
    {
        return $this->relationLoaded('resultados')
            ? $this->resultados->firstWhere('estudiante_id', $estudiante->id)
            : $this->resultados()->where('estudiante_id', $estudiante->id)->first();
    }

    /**
     * Calcula el puntaje (0-20) comparando las respuestas del estudiante
     * (índice de opción elegida por pregunta) contra `respuesta_correcta`.
     *
     * @param  array<int, int>  $respuestas
     */
    public function calcularPuntaje(array $respuestas): float
    {
        $preguntas = $this->preguntas ?? [];

        if (count($preguntas) === 0) {
            return 0.0;
        }

        $correctas = 0;

        foreach ($preguntas as $indice => $pregunta) {
            $elegida = $respuestas[$indice] ?? null;

            // Comparación por valor, no por tipo: el índice elegido llega
            // desde un <input type="radio"> (siempre string en el navegador),
            // pero `respuesta_correcta` puede quedar guardado como int si el
            // quiz se creó por seeder/factory/API en vez de la pantalla real.
            // Con === estricto, "1" === 1 es false y se contaba como
            // incorrecta una respuesta que en realidad era correcta.
            if ($elegida !== null && (int) $elegida === (int) $pregunta['respuesta_correcta']) {
                $correctas++;
            }
        }

        return round(($correctas / count($preguntas)) * 20, 2);
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
     * Estadística por pregunta para el profesor (Módulo 4 del documento del
     * proyecto: "el profesor revisa qué preguntas o temas tuvieron más
     * errores"). Ordenado por tasa de error descendente, para que las
     * preguntas más problemáticas aparezcan primero.
     *
     * @return array<int, array{indice: int, enunciado: string, total_respuestas: int, correctas: int, incorrectas: int, tasa_error: float, distribucion: array<int, array{opcion: string, elegida_por: int, es_correcta: bool}>}>
     */
    public function estadisticasPorPregunta(): array
    {
        $resultados = $this->relationLoaded('resultados') ? $this->resultados : $this->resultados()->get();
        $totalRespuestas = $resultados->count();

        return collect($this->preguntas ?? [])
            ->map(function (array $pregunta, int $indice) use ($resultados, $totalRespuestas) {
                $elegidas = $resultados->pluck('respuestas.'.$indice)->filter(fn ($v) => $v !== null);
                $correcta = (int) $pregunta['respuesta_correcta'];
                $correctas = $elegidas->filter(fn ($v) => (int) $v === $correcta)->count();

                $distribucion = collect($pregunta['opciones'])
                    ->map(fn (string $opcion, int $indiceOpcion) => [
                        'opcion' => $opcion,
                        'elegida_por' => $elegidas->filter(fn ($v) => (int) $v === $indiceOpcion)->count(),
                        'es_correcta' => $indiceOpcion === $correcta,
                    ])
                    ->values()
                    ->all();

                return [
                    'indice' => $indice,
                    'enunciado' => $pregunta['enunciado'],
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
