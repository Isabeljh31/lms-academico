<?php

namespace App\Notifications;

use App\Models\Curso;
use Illuminate\Notifications\Notification;

/**
 * ERS RF-33: Notificar al estudiante cuando es matriculado en un curso.
 */
class MatriculadoEnCurso extends Notification
{
    public function __construct(protected Curso $curso, protected string $origen = 'manual') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $fuente = $this->origen === 'api' ? 'mediante integración externa' : 'por el Supervisor';
        return [
            'titulo'  => 'Matriculado en curso',
            'mensaje' => "Has sido matriculado {$fuente} en el curso \"{$this->curso->nombre}\".",
            'url'     => route('cursos.show', $this->curso->id),
            'icono'   => '🎓',
        ];
    }
}
