<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Usuarios\GestorCuentas;

class UsuarioPolicy
{
    public function verListado(User $actor): bool
    {
        return $actor->hasRole('supervisor');
    }

    /**
     * Cada cuenta tiene un único rol (ERS 2.3). El Supervisor solo puede
     * corregirlo si se equivocó al crear la cuenta: nunca la suya, nunca la de
     * otro Supervisor y solo mientras la cuenta no tenga historial académico.
     */
    public function corregirRol(User $actor, User $objetivo): bool
    {
        return $actor->hasRole('supervisor')
            && $actor->isNot($objetivo)
            && in_array($objetivo->rolPrincipal(), GestorCuentas::ROLES_CREABLES, true)
            && ! $objetivo->tieneHistorialAcademico();
    }
}
