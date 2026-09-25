<?php

namespace App\Policies;

use App\Models\User;

class UsuarioPolicy
{
    public function verListado(User $actor): bool
    {
        return $actor->hasRole('supervisor');
    }

    public function actualizarRol(User $actor, User $objetivo): bool
    {
        return $actor->hasRole('supervisor') && $actor->isNot($objetivo);
    }
}
