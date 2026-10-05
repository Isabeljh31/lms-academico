<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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
        'publicar_en',
        'notificado',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'publicar_en' => 'datetime',
            'notificado' => 'boolean',
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

    /**
     * ERS RF-87: activo y con su fecha de publicación ya alcanzada.
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true)
            ->where(fn ($q) => $q->whereNull('publicar_en')->orWhere('publicar_en', '<=', now()));
    }

    public function estaPublicado(): bool
    {
        return $this->activo && ($this->publicar_en === null || $this->publicar_en->lte(now()));
    }

    /**
     * Usuarios que deben recibir la notificación del comunicado institucional.
     *
     * @return Collection<int, User>
     */
    public function destinatarios(): Collection
    {
        $roles = match ($this->destinatario) {
            'profesores' => ['profesor'],
            'estudiantes' => ['estudiante'],
            default => ['profesor', 'estudiante'],
        };

        return User::role($roles)->where('activo', true)->get();
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
        if ($rol === 'supervisor') {
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
