<?php

namespace App\Notifications;

use App\Models\Tarea;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * ERS RF-84: aviso de cambio en la fecha límite de una tarea.
 */
class CambioFechaTarea extends Notification
{
    public function __construct(protected Tarea $tarea, protected Carbon $fechaAnterior) {}

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
        return [
            'titulo' => 'Cambio de fecha',
            'mensaje' => "La tarea \"{$this->tarea->titulo}\" ahora vence el {$this->tarea->fecha_entrega->formatoFechaHora()} (antes {$this->fechaAnterior->formatoFechaHora()}).",
            'url' => route('cursos.tareas.show', [$this->tarea->curso_id, $this->tarea->id]),
        ];
    }
}
