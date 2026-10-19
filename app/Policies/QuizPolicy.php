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

    /** Consulta de solo lectura: Supervisor y Profesor del curso. */
    public function supervisar(User $usuario, Quiz $quiz): bool
    {
        return Gate::forUser($usuario)->allows('supervisar', $quiz->curso);
    }

    /**
     * El estudiante solo ve quizzes publicados (RF-38) de un curso visible
     * en el que tiene matrícula activa.
     */
    public function ver(User $usuario, Quiz $quiz): bool
    {
        if ($this->supervisar($usuario, $quiz)) {
            return true;
        }

        return $usuario->hasRole('estudiante')
            && $quiz->esVisibleParaEstudiante()
            && Gate::forUser($usuario)->allows('ver', $quiz->curso);
    }

    /**
     * RF-45: puede rendir mientras el quiz esté abierto (fechas) y le queden
     * intentos. Un curso archivado ya no admite intentos nuevos.
     */
    public function responder(User $usuario, Quiz $quiz): bool
    {
        return $usuario->hasRole('estudiante')
            && $this->ver($usuario, $quiz)
            && ! $quiz->curso->estaFinalizado()
            && $quiz->estaAbierto()
            && $quiz->quedanIntentos($usuario);
    }

    public function eliminar(User $usuario, Quiz $quiz): bool
    {
        return $this->gestionar($usuario, $quiz) && $quiz->puedeEliminarse();
    }
}
