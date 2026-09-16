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

    protected $fillable = [
        'curso_id',
        'modulo_id',
        'tema',
        'preguntas',
    ];

    protected function casts(): array
    {
        return [
            'preguntas' => 'array', // Deserialización nativa de JSONB
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
            if (($respuestas[$indice] ?? null) === $pregunta['respuesta_correcta']) {
                $correctas++;
            }
        }

        return round(($correctas / count($preguntas)) * 20, 2);
    }
}
