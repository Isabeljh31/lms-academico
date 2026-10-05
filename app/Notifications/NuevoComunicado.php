<?php

namespace App\Notifications;

use App\Models\Comunicado;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * ERS RF-84 y RF-87: aviso de un comunicado institucional o de un anuncio
 * del curso.
 */
class NuevoComunicado extends Notification
{
    public function __construct(protected Comunicado $comunicado) {}

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
        $origen = $this->comunicado->curso_id
            ? 'Anuncio en '.$this->comunicado->curso?->nombre
            : 'Comunicado institucional';

        return [
            'titulo' => "{$origen}: {$this->comunicado->titulo}",
            'mensaje' => Str::limit($this->comunicado->contenido, 140),
            'url' => $this->comunicado->curso_id
                ? route('cursos.show', $this->comunicado->curso_id)
                : route('dashboard'),
        ];
    }
}
