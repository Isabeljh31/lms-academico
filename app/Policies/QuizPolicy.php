<?php

namespace App\Policies;

use App\Models\Curso;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class QuizPolicy
{
    public function crear(User $usuario, Curso $curso): bool
    {
        return Gate::forUser($usuario)->allows('gestionar', $curso);
    }

    public function gestionar(User $usuario, Quiz $quiz): bool
    {
        return Gate::forUser($usuario)->allows('gestionar', $quiz->curso);
    }

    public function ver(User $usuario, Quiz $quiz): bool
    {
        if ($this->gestionar($usuario, $quiz)) {
            return true;
        }

        return $quiz->visible
            && $usuario->hasRole('estudiante')
            && $quiz->curso->inscripciones()->where('estudiante_id', $usuario->id)->exists();
    }

    public function responder(User $usuario, Quiz $quiz): bool
    {
        return $this->ver($usuario, $quiz)
            && $usuario->hasRole('estudiante')
            && ! $quiz->resultadoDe($usuario);
    }

    public function eliminar(User $usuario, Quiz $quiz): bool
    {
        return $this->gestionar($usuario, $quiz) && $quiz->puedeEliminarse();
    }
}
