<?php

namespace App\Notifications;

use App\Models\Entrega;
use Illuminate\Notifications\Notification;

/**
 * ERS RF-49: el profesor devuelve la entrega al estudiante para que la corrija.
 */
class EntregaDevuelta extends Notification
{
    public function __construct(protected Entrega $entrega) {}

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
        $tarea = $this->entrega->tarea;

        return [
            'titulo' => 'Entrega devuelta para corrección',
            'mensaje' => "Tu profesor devolvió tu entrega de \"{$tarea->titulo}\". Revisa sus comentarios y vuelve a entregarla.",
            'url' => route('cursos.tareas.show', [$tarea->curso_id, $tarea->id]),
        ];
    }
}
