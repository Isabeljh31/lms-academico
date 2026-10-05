<?php

namespace App\Notifications;

use App\Models\Mensaje;
use Illuminate\Notifications\Notification;

/**
 * ERS RF-83: Notificación cuando se recibe un mensaje nuevo.
 */
class NuevoMensaje extends Notification
{
    public function __construct(protected Mensaje $mensaje) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => 'Mensaje nuevo de '.$this->mensaje->remitente?->name,
            'mensaje' => "Asunto: \"{$this->mensaje->asunto}\"",
            'url' => route('mensajeria.bandeja'),
        ];
    }
}
