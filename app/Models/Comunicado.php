<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comunicado extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'curso_id',
        'titulo',
        'contenido',
        'prioridad',
        'destinatario',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeInstitucionales($query)
    {
        return $query->whereNull('curso_id');
    }

    public function scopeDelCurso($query, int $cursoId)
    {
        return $query->where('curso_id', $cursoId);
    }

    public function scopeParaRol($query, string $rol)
    {
        if ($rol === 'administrador') {
            return $query;
        }

        if ($rol === 'profesor') {
            return $query->whereIn('destinatario', ['profesores', 'todos']);
        }

        if ($rol === 'estudiante') {
            return $query->whereIn('destinatario', ['estudiantes', 'todos']);
        }

        return $query->where('destinatario', 'todos');
    }
}
