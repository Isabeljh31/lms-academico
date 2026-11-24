<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/**
 * Catálogo «Sedes y salones» (sedes, tipos de salón, salones) y la
 * asignación de salones a las clases de un curso: solo el Supervisor.
 */
class SalonPolicy
{
    public function gestionar(User $usuario): bool
    {
        return $usuario->hasRole(Rol::Supervisor->value);
    }
}
