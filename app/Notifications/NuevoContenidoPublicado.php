<?php

namespace App\Notifications;

use App\Models\Leccion;
use App\Models\Modulo;
use Illuminate\Notifications\Notification;

/**
 * ERS RF-84: aviso de nuevo contenido publicado (lección o módulo), incluido
 * el contenido Programado cuando llega su fecha de publicación.
 */
class NuevoContenidoPublicado extends Notification
{
    public function __construct(protected Leccion|Modulo $contenido) {}

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
        $esModulo = $this->contenido instanceof Modulo;
        $curso = $esModulo ? $this->contenido->curso : $this->contenido->modulo->curso;

        return [
            'titulo' => 'Nuevo contenido',
            'mensaje' => $esModulo
                ? "Se publicó el módulo \"{$this->contenido->titulo}\" en {$curso->nombre}."
                : "Se publicó la lección \"{$this->contenido->titulo}\" en {$curso->nombre}.",
            'url' => route('cursos.show', $curso),
        ];
    }
}
