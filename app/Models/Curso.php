<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    use HasFactory;

    protected $table = 'cursos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'profesor_id',
        'imagen_portada_url',
    ];

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    public function modulos(): HasMany
    {
        return $this->hasMany(Modulo::class, 'curso_id')->orderBy('orden');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'curso_id');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'curso_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'curso_id');
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'curso_id');
    }
}
