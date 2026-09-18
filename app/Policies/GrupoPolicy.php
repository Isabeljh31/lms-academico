<?php

namespace App\Policies;

use App\Models\Curso;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class GrupoPolicy
{
    public function crear(User $usuario, Curso $curso): bool
    {
        return Gate::forUser($usuario)->allows('gestionar', $curso);
    }

    public function gestionar(User $usuario, Grupo $grupo): bool
    {
        return Gate::forUser($usuario)->allows('gestionar', $grupo->curso);
    }
}
