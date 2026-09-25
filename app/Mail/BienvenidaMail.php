<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * ERS RF-04: Correo de bienvenida con contraseña temporal.
 * Se envía al crear una cuenta manualmente (Supervisor) o vía API.
 *
 * NOTA: Para activar el envío real, configurar MAIL_* en .env y
 * descomentar las llamadas Mail::to(...)->send(...) en:
 *   - app/Livewire/Usuarios/Index.php (crearUsuario)
 *   - app/Http/Controllers/Api/MatriculaController.php (sincronizar)
 */
class BienvenidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $usuario,
        public string $passwordTemporal
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenido a LearnUp — Tus credenciales de acceso',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bienvenida',
            with: [
                'usuario'          => $this->usuario,
                'passwordTemporal' => $this->passwordTemporal,
                'urlLogin'         => route('login'),
            ],
        );
    }
}
