<?php

namespace App\Policies;

use App\Models\Entrega;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class EntregaPolicy
{
    /**
     * ERS RF-42: el estudiante matriculado entrega una tarea visible para él,
     * ya iniciada, en un curso no finalizado.
     */
    public function crear(User $usuario, Tarea $tarea): bool
    {
        return $usuario->hasRole('estudiante')
            && Gate::forUser($usuario)->allows('ver', $tarea)
            && $tarea->haIniciado()
            && ! $tarea->curso->estaFinalizado();
    }

    /**
     * Cada estudiante solo ve su propia entrega (sin IDOR); el profesor del
     * curso y el Supervisor (solo lectura) ven todas.
     */
    public function ver(User $usuario, Entrega $entrega): bool
    {
        return $usuario->id === $entrega->estudiante_id
            || Gate::forUser($usuario)->allows('supervisar', $entrega->tarea->curso);
    }

    /**
     * ERS RF-49: califica el profesor del curso, mientras no esté finalizado.
     */
    public function calificar(User $usuario, Entrega $entrega): bool
    {
        $curso = $entrega->tarea->curso;

        return Gate::forUser($usuario)->allows('calificar', $curso) && ! $curso->estaFinalizado();
    }
}
