<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Models\User;

/**
 * Solicitud de cambio de clase (reprogramación): la pide, la cancela y
 * responde el sustento el Profesor del curso (titular o co-docente,
 * CursoPolicy::gestionar); solo el Supervisor la ve en su bandeja, la
 * aprueba o rechaza, pide sustento y revierte. El archivo de sustento solo
 * lo descargan el Supervisor y el Profesor que pidió la reprogramación:
 * nunca los estudiantes ni otros profesores. Las reglas de la clase (futura, sin lista, curso
 * activo) las aplica SolicitudesCambioClase.
 */
class SolicitudCambioClasePolicy
{
    /** Pedir el cambio de una clase de un curso que el Profesor gestiona. */
    public function solicitar(User $usuario, SesionClase $sesion): bool
    {
        return $usuario->can('gestionar', $sesion->curso);
    }

    /** Ver la solicitud: el Supervisor o un docente del curso. */
    public function view(User $usuario, SolicitudCambioClase $solicitud): bool
    {
        return $this->decidir($usuario) || $usuario->can('gestionar', $solicitud->curso);
    }

    /** Cancelarla mientras esté pendiente: un docente del curso. */
    public function cancelar(User $usuario, SolicitudCambioClase $solicitud): bool
    {
        return $usuario->can('gestionar', $solicitud->curso);
    }

    /** Aprobar o rechazar: solo el Supervisor. */
    public function decidir(User $usuario, ?SolicitudCambioClase $solicitud = null): bool
    {
        return $usuario->hasRole(Rol::Supervisor->value);
    }

    /** Pedir sustento de una reprogramación en revisión: solo el Supervisor. */
    public function pedirSustento(User $usuario, ?SolicitudCambioClase $solicitud = null): bool
    {
        return $this->decidir($usuario);
    }

    /** Revertir una reprogramación aplicada: solo el Supervisor. */
    public function revertir(User $usuario, ?SolicitudCambioClase $solicitud = null): bool
    {
        return $this->decidir($usuario);
    }

    /** Responder el sustento pedido: un docente del curso. */
    public function responderSustento(User $usuario, SolicitudCambioClase $solicitud): bool
    {
        return $usuario->can('gestionar', $solicitud->curso);
    }

    /** Ver o descargar el sustento: el Supervisor o el Profesor dueño de la reprogramación. */
    public function verSustento(User $usuario, SolicitudCambioClase $solicitud): bool
    {
        return $this->decidir($usuario) || ($solicitud->solicitado_por !== null && $solicitud->solicitado_por === $usuario->id);
    }
}
