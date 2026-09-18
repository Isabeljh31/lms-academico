<?php

namespace App\Policies;

use App\Models\PropuestaBot;
use App\Models\User;

class PropuestaBotPolicy
{
    public function responder(User $usuario, PropuestaBot $propuesta): bool
    {
        return $usuario->is($propuesta->usuario) && $propuesta->estado === 'pendiente';
    }
}
