<?php

namespace App\Notifications;

use App\Models\SolicitudCambioClase;
use Illuminate\Notifications\Notification;

/**
 * Aviso al Profesor que pidió la reprogramación: el Supervisor la revirtió y
 * la clase volvió a su horario original.
 */
class ReprogramacionRevertida extends Notification
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
        $comentario = filled($this->solicitud->comentario_reversion) ? " {$this->solicitud->comentario_reversion}" : '';

        return [
            'titulo' => 'Reprogramación revertida',
            'mensaje' => "Se revirtió la reprogramación de la clase de {$curso->nombre}: vuelve al {$this->solicitud->fecha_original->format('d/m/Y H:i')}.{$comentario}",
            'url' => route('asistencia.index', $curso),
            'solicitud_id' => $this->solicitud->id,
        ];
    }
}
