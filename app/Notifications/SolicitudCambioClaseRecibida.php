<?php

namespace App\Notifications;

use App\Models\SolicitudCambioClase;
use Illuminate\Notifications\Notification;

/**
 * Aviso al Supervisor: un Profesor pidió cambiar una clase.
 */
class SolicitudCambioClaseRecibida extends Notification
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
        $profesor = $this->solicitud->solicitante?->name ?? 'Un profesor';

        return [
            'titulo' => 'Reprogramación en revisión',
            'mensaje' => "{$profesor} pidió reprogramar la clase del {$this->solicitud->fecha_original->format('d/m/Y H:i')} de {$curso->nombre}.",
            'url' => route('admin.solicitudes-cambio-clase.index'),
            'solicitud_id' => $this->solicitud->id,
        ];
    }
}
