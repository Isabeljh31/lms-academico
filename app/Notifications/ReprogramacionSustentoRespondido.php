<?php

namespace App\Notifications;

use App\Models\SolicitudCambioClase;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los Supervisores: el Profesor respondió el sustento pedido de una
 * reprogramación en revisión.
 */
class ReprogramacionSustentoRespondido extends Notification
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
        $profesor = $this->solicitud->solicitante?->name ?? 'El profesor';

        return [
            'titulo' => 'Sustento recibido',
            'mensaje' => "{$profesor} respondió el sustento para reprogramar la clase del {$this->solicitud->fecha_original->format('d/m/Y H:i')} de {$curso->nombre}.",
            'url' => route('admin.solicitudes-cambio-clase.index'),
            'solicitud_id' => $this->solicitud->id,
        ];
    }
}
