<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultadoQuiz extends Model
{
    use HasFactory;

    protected $table = 'resultados_quiz';

    protected $fillable = [
        'quiz_id',
        'estudiante_id',
        'respuestas',
        'puntaje',
    ];

    protected function casts(): array
    {
        return [
            'respuestas' => 'array', // Deserialización nativa de JSONB
            'puntaje' => 'decimal:2',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }
}
