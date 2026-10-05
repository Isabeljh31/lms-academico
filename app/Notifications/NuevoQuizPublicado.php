<?php

namespace App\Notifications;

use App\Models\Quiz;
use Illuminate\Notifications\Notification;

/**
 * ERS RF-84: aviso de un quiz nuevo publicado.
 */
class NuevoQuizPublicado extends Notification
{
    public function __construct(protected Quiz $quiz) {}

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
        $limite = $this->quiz->fecha_limite ? ', disponible hasta el '.$this->quiz->fecha_limite->formatoFechaHora() : '';

        return [
            'titulo' => 'Nuevo quiz',
            'mensaje' => "Se publicó el quiz \"{$this->quiz->tema}\" en {$this->quiz->curso->nombre}{$limite}.",
            'url' => route('cursos.quizzes.show', [$this->quiz->curso_id, $this->quiz->id]),
        ];
    }
}
