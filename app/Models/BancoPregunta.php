<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ERS RF-47: Banco de Preguntas reutilizables por curso y tema.
 * Usa el mismo formato de pregunta que Quiz (ver Quiz::TIPOS_PREGUNTA).
 */
class BancoPregunta extends Model
{
    protected $table = 'banco_preguntas';

    protected $fillable = [
        'curso_id', 'tema', 'pregunta', 'tipo',
        'opciones', 'respuestas_correctas', 'explicacion', 'dificultad', 'creado_por',
    ];

    protected $casts = [
        'opciones' => 'array',
        'respuestas_correctas' => 'array',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Textos de las opciones correctas.
     *
     * @return array<int, string>
     */
    public function opcionesCorrectas(): array
    {
        return collect($this->respuestas_correctas ?? [])
            ->map(fn ($indice) => $this->opciones[(int) $indice] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Convierte la pregunta del banco al formato de pregunta de un Quiz.
     *
     * @return array<string, mixed>
     */
    public function aPreguntaDeQuiz(): array
    {
        $correctas = array_map('intval', $this->respuestas_correctas ?? [0]);

        return [
            'enunciado' => $this->pregunta,
            'tipo' => $this->tipo,
            'opciones' => array_values($this->opciones ?? []),
            'respuesta_correcta' => $correctas[0] ?? 0,
            'respuestas_correctas' => $correctas,
            'puntaje' => 1,
            'retroalimentacion' => $this->explicacion ?? '',
        ];
    }
}
