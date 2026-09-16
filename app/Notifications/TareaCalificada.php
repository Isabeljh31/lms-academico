<?php

namespace App\Notifications;

use App\Models\Entrega;
use Illuminate\Notifications\Notification;

class TareaCalificada extends Notification
{
    public function __construct(protected Entrega $entrega) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tarea = $this->entrega->tarea;

        return [
            'titulo' => 'Tarea calificada',
            'mensaje' => "\"{$tarea->titulo}\" fue calificada: {$this->entrega->nota}/20.",
            'url' => route('cursos.tareas.show', [$tarea->curso_id, $tarea->id]),
        ];
    }
}
