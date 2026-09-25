<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ERS RF-47: Banco de Preguntas reutilizables por curso/tema.
 */
class BancoPregunta extends Model
{
    protected $table = 'banco_preguntas';

    protected $fillable = [
        'curso_id', 'tema', 'pregunta', 'tipo',
        'opciones', 'explicacion', 'dificultad', 'creado_por',
    ];

    protected $casts = [
        'opciones' => 'array',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function opcionesCorrectas(): array
    {
        return collect($this->opciones ?? [])
            ->filter(fn ($op) => $op['es_correcta'] ?? false)
            ->values()
            ->toArray();
    }
}
