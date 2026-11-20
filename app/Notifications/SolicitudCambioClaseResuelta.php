<?php

namespace App\Notifications;

use App\Models\SolicitudCambioClase;
use Illuminate\Notifications\Notification;

/**
 * Aviso al Profesor que pidió el cambio: el Supervisor lo aprobó o lo rechazó.
 */
class SolicitudCambioClaseResuelta extends Notification
{
    public function __construct(protected SolicitudCambioClase $solicitud) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $curso = $this->solicitud->curso;
        $original = $this->solicitud->fecha_original->format('d/m/Y H:i');

        $mensaje = $this->solicitud->estado === 'aprobada'
            ? "El supervisor aprobó tu solicitud: la clase del {$original} de {$curso->nombre} quedó reprogramada para el {$this->solicitud->fecha_nueva->format('d/m/Y H:i')}. Ya se avisó a tus estudiantes."
            : "Se rechazó la reprogramación de la clase del {$original} de {$curso->nombre}: {$this->solicitud->comentario_decision}";

        return [
            'titulo' => $this->solicitud->estado === 'aprobada' ? 'Clase reprogramada' : 'Reprogramación rechazada',
            'mensaje' => $mensaje,
            'url' => route('asistencia.index', $curso),
            'solicitud_id' => $this->solicitud->id,
        ];
    }
}
