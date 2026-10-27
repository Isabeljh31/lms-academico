<?php

namespace App\Notifications;

use App\Models\Debate;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los estudiantes del curso cuando el Profesor publica un debate
 * (mismo patrón que NuevoContenidoPublicado). Las respuestas no notifican.
 */
class NuevoDebatePublicado extends Notification
{
    public function __construct(protected Debate $debate) {}

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
        $curso = $this->debate->curso;

        return [
            'titulo' => 'Nuevo debate',
            'mensaje' => "Se abrió el debate \"{$this->debate->titulo}\" en {$curso->nombre}.",
            'url' => route('cursos.debates.show', [$curso, $this->debate]),
        ];
    }
}
