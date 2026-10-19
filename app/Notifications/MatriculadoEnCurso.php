<?php

namespace App\Notifications;

use App\Models\Curso;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ERS RF-33 y regla de negocio 10: toda matrícula (manual o por API) notifica
 * al estudiante dentro de la plataforma y por correo, con enlace al LMS.
 */
class MatriculadoEnCurso extends Notification
{
    public function __construct(protected Curso $curso, protected string $origen = 'manual') {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Tienes acceso al curso {$this->curso->nombre} en LearnUp")
            ->greeting("Hola {$notifiable->name}:")
            ->line("Fuiste matriculado {$this->fuente()} en el curso \"{$this->curso->nombre}\".")
            ->action('Ir al curso', route('cursos.show', $this->curso))
            ->line('Si es tu primer ingreso, usa las credenciales que recibiste en el correo de bienvenida.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => 'Matriculado en curso',
            'mensaje' => "Fuiste matriculado {$this->fuente()} en el curso \"{$this->curso->nombre}\".",
            'url' => route('cursos.show', $this->curso->id),
        ];
    }

    protected function fuente(): string
    {
        return $this->origen === 'api' ? 'mediante integración externa' : 'por el Supervisor';
    }
}
