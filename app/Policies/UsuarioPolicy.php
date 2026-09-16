<?php

namespace App\Policies;

use App\Models\User;

class UsuarioPolicy
{
    public function verListado(User $actor): bool
    {
        return $actor->hasRole('administrador');
    }

    public function actualizarRol(User $actor, User $objetivo): bool
    {
        return $actor->hasRole('administrador') && $actor->isNot($objetivo);
    }
}
