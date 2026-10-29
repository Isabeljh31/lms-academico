<?php

namespace App\Policies;

use App\Models\Curso;
use App\Models\Debate;
use App\Models\DebateComentario;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Permisos de los Debates del curso (extensión fuera del ERS). Se apoya en
 * CursoPolicy y no agrega permisos nuevos al Supervisor:
 *
 * - verLista / ver: quien puede ver el curso. El estudiante solo ve los
 *   debates publicados; Profesor del curso y Supervisor ven también los
 *   borradores (consulta, como en tareas y quizzes).
 * - crear / gestionar: solo el Profesor del curso, con el curso no archivado.
 * - comentar: Profesor del curso y estudiante matriculado activo, en un
 *   debate publicado y abierto. El Supervisor solo lee.
 * - editarComentario: el autor, mientras pueda comentar.
 * - eliminarComentario: el autor mientras pueda comentar, o el Profesor del
 *   curso en cualquier momento (moderación).
 */
class DebatePolicy
{
    public function verLista(User $usuario, Curso $curso): bool
    {
        return Gate::forUser($usuario)->allows('ver', $curso);
    }

    public function ver(User $usuario, Debate $debate): bool
    {
        if (! Gate::forUser($usuario)->allows('ver', $debate->curso)) {
            return false;
        }

        return $debate->estaPublicado() || Gate::forUser($usuario)->allows('supervisar', $debate->curso);
    }

    public function crear(User $usuario, Curso $curso): bool
    {
        return Gate::forUser($usuario)->allows('gestionar', $curso) && ! $curso->estaFinalizado();
    }

    public function gestionar(User $usuario, Debate $debate): bool
    {
        return $this->crear($usuario, $debate->curso);
    }

    public function comentar(User $usuario, Debate $debate): bool
    {
        if (! $debate->estaPublicado() || $debate->estaCerrado() || $debate->curso->estaFinalizado()) {
            return false;
        }

        if (Gate::forUser($usuario)->allows('gestionar', $debate->curso)) {
            return true;
        }

        return $usuario->hasRole('estudiante') && Gate::forUser($usuario)->allows('ver', $debate->curso);
    }

    public function editarComentario(User $usuario, DebateComentario $comentario): bool
    {
        return $comentario->user_id === $usuario->id && $this->comentar($usuario, $comentario->debate);
    }

    public function eliminarComentario(User $usuario, DebateComentario $comentario): bool
    {
        return $this->gestionar($usuario, $comentario->debate) || $this->editarComentario($usuario, $comentario);
    }
}
