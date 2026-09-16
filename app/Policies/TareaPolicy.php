<?php

namespace App\Policies;

use App\Models\Curso;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TareaPolicy
{
    public function crear(User $usuario, Curso $curso): bool
    {
        return Gate::forUser($usuario)->allows('gestionar', $curso);
    }

    public function gestionar(User $usuario, Tarea $tarea): bool
    {
        return Gate::forUser($usuario)->allows('gestionar', $tarea->curso);
    }

    public function ver(User $usuario, Tarea $tarea): bool
    {
        if ($this->gestionar($usuario, $tarea)) {
            return true;
        }

        return $usuario->hasRole('estudiante')
            && $tarea->curso->inscripciones()->where('estudiante_id', $usuario->id)->exists();
    }
}
