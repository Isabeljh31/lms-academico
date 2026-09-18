<?php

namespace App\Policies;

use App\Models\Curso;
use App\Models\User;

class CursoPolicy
{
    public function ver(User $usuario, Curso $curso): bool
    {
        // Cualquier usuario autenticado puede ver el catálogo y el detalle:
        // el profesor dueño, el administrador, y cualquier estudiante
        // (inscrito o no, para poder inscribirse).
        return true;
    }

    public function crear(User $usuario): bool
    {
        return $usuario->hasAnyRole(['profesor', 'administrador']);
    }

    public function gestionar(User $usuario, Curso $curso): bool
    {
        return $usuario->hasRole('administrador')
            || ($usuario->hasRole('profesor') && $usuario->is($curso->profesor));
    }

    public function inscribirse(User $usuario, Curso $curso): bool
    {
        return $usuario->hasRole('estudiante');
    }

    /**
     * Ver el progreso/boleta de notas de un curso (Módulo 9): el dueño del
     * curso o administrador, o el propio estudiante si está inscrito.
     */
    public function verProgreso(User $usuario, Curso $curso): bool
    {
        return $this->gestionar($usuario, $curso)
            || $curso->inscripciones()->where('estudiante_id', $usuario->id)->exists();
    }
}
