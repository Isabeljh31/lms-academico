<?php

namespace App\Notifications;

use App\Models\SolicitudCambioClase;
use Illuminate\Notifications\Notification;

/**
 * Aviso al Profesor que pidió la reprogramación: el Supervisor necesita un
 * sustento para decidir.
 */
class ReprogramacionSustentoSolicitado extends Notification
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

        return [
            'titulo' => 'Sustento solicitado',
            'mensaje' => "El supervisor pidió sustento para reprogramar la clase del {$this->solicitud->fecha_original->format('d/m/Y H:i')} de {$curso->nombre}: {$this->solicitud->sustento_pedido}",
            'url' => route('asistencia.index', $curso),
            'solicitud_id' => $this->solicitud->id,
        ];
    }
}
