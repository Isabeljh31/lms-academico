<?php

namespace App\Notifications;

use App\Models\Tarea;
use Illuminate\Notifications\Notification;

class NuevaTareaAsignada extends Notification
{
    public function __construct(protected Tarea $tarea) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => 'Nueva tarea',
            'mensaje' => "Se publicó \"{$this->tarea->titulo}\" en {$this->tarea->curso->nombre}, vence el ".$this->tarea->fecha_entrega->formatoFecha().'.',
            'url' => route('cursos.tareas.show', [$this->tarea->curso_id, $this->tarea->id]),
        ];
    }
}
