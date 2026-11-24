<?php

namespace App\Notifications;

use App\Models\SesionClase;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los docentes del curso: una clase pasó a remota (reprogramación
 * aplicada sola o aprobada por el Supervisor) y no hay enlace de reunión
 * ni de la clase ni del curso. Nunca incluye un enlace de reunión: solo la
 * pantalla del curso donde el Profesor lo agrega. «sesion_id» va primero
 * para detectar un aviso ya enviado de la misma clase.
 */
class EnlaceRemotoFaltante extends Notification
{
    public function __construct(protected SesionClase $sesion) {}

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
        $curso = $this->sesion->curso;

        return [
            'sesion_id' => $this->sesion->id,
            'titulo' => 'Falta el enlace de la clase remota',
            'mensaje' => "La clase del {$this->sesion->fecha->format('d/m/Y H:i')} de {$curso->nombre} será remota y aún no tiene enlace. Agrega el enlace de clases remotas del curso.",
            'url' => route('cursos.show', $curso),
        ];
    }
}
