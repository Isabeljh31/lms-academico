<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modulo extends Model
{
    use HasFactory;

    protected $table = 'modulos';

    protected $fillable = [
        'curso_id',
        'titulo',
        'orden',
        'materiales',
    ];

    protected function casts(): array
    {
        return [
            'materiales' => 'array',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'modulo_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'modulo_id');
    }
}
