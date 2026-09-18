<?php

namespace App\Policies;

use App\Models\Entrega;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class EntregaPolicy
{
    public function crear(User $usuario, Tarea $tarea): bool
    {
        return $usuario->hasRole('estudiante')
            && $tarea->curso->inscripciones()->where('estudiante_id', $usuario->id)->exists();
    }

    public function ver(User $usuario, Entrega $entrega): bool
    {
        return $usuario->is($entrega->estudiante)
            || Gate::forUser($usuario)->allows('gestionar', $entrega->tarea->curso);
    }

    public function calificar(User $usuario, Entrega $entrega): bool
    {
        return Gate::forUser($usuario)->allows('gestionar', $entrega->tarea->curso);
    }
}
