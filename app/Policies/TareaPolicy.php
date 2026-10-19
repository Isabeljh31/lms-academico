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

    /** Consulta de solo lectura: Supervisor y Profesor del curso. */
    public function supervisar(User $usuario, Tarea $tarea): bool
    {
        return Gate::forUser($usuario)->allows('supervisar', $tarea->curso);
    }

    /**
     * El estudiante solo ve tareas publicadas (RF-38) de un curso visible en
     * el que tiene matrícula activa. Una tarea grupal asignada a un grupo
     * concreto solo la ven los integrantes de ese grupo (RF-76).
     */
    public function ver(User $usuario, Tarea $tarea): bool
    {
        if ($this->supervisar($usuario, $tarea)) {
            return true;
        }

        if (! $usuario->hasRole('estudiante') || ! $tarea->esVisibleParaEstudiante()) {
            return false;
        }

        if (! Gate::forUser($usuario)->allows('ver', $tarea->curso)) {
            return false;
        }

        return $tarea->grupo_asignado_id === null
            || $tarea->grupoAsignado?->estudiantes()->whereKey($usuario->id)->exists();
    }

    /**
     * Eliminar de verdad solo si nadie entregó todavía (ver
     * Tarea::puedeEliminarse). Con entregas de por medio, la UI debe
     * ofrecer "ocultar" en vez de este permiso.
     */
    public function eliminar(User $usuario, Tarea $tarea): bool
    {
        return $this->gestionar($usuario, $tarea) && $tarea->puedeEliminarse();
    }
}
