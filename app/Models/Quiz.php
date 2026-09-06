<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
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
}