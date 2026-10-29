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
        'curso_id', 'unidad', 'tema', 'pregunta', 'tipo',
        'opciones', 'respuestas_correctas', 'explicacion', 'dificultad', 'puntaje', 'creado_por',
    ];

    protected $casts = [
        'opciones' => 'array',
        'respuestas_correctas' => 'array',
        'puntaje' => 'decimal:2',
    ];

    /**
     * Unidades del curso, en orden. "tema" es el contenido dentro de la
     * unidad; las preguntas anteriores a este cambio no tienen unidad (null).
     *
     * @var list<string>
     */
    public const UNIDADES = ['T1', 'T2', 'T3', 'T4'];

    /** Valor del filtro para las preguntas sin unidad. */
    public const SIN_UNIDAD = 'sin';

    /**
     * Unidades válidas del banco de un curso, en su orden. Hoy es la lista
     * fija UNIDADES; es el único punto a cambiar si cada curso define las suyas.
     *
     * @return list<string>
     */
    public static function unidadesDe(Curso $curso): array
    {
        return self::UNIDADES;
    }

    /**
     * Unidades anteriores a la actual (según el orden): se consideran archivadas.
     *
     * @return list<string>
     */
    public static function unidadesArchivadas(Curso $curso, string $actual): array
    {
        $unidades = self::unidadesDe($curso);
        $posicion = array_search($actual, $unidades, true);

        return $posicion === false ? [] : array_slice($unidades, 0, $posicion);
    }

    /** Unidad escrita como " t1 " o "T 1" => "T1"; null si no es una de UNIDADES. */
    public static function normalizarUnidad(?string $valor): ?string
    {
        $unidad = mb_strtoupper(preg_replace('/\s+/u', '', (string) $valor));

        return in_array($unidad, self::UNIDADES, true) ? $unidad : null;
    }

    /** Clave normalizada para detectar duplicados (mismo tema y mismo enunciado, sin distinguir mayúsculas). */
    public static function claveDuplicado(string $tema, string $pregunta): string
    {
        return self::claveEnunciado($tema).'|'.self::claveEnunciado($pregunta);
    }

    /** Texto normalizado (espacios y mayúsculas); reconoce la copia de una pregunta dentro de un quiz. */
    public static function claveEnunciado(string $texto): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($texto)));
    }

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
            'puntaje' => (float) ($this->puntaje ?? 1),
            'retroalimentacion' => $this->explicacion ?? '',
        ];
    }
}
