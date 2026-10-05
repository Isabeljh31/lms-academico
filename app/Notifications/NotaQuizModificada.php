<?php

namespace App\Notifications;

use App\Models\Quiz;
use Illuminate\Notifications\Notification;

/**
 * ERS RF-53: aviso al estudiante cuando la nota de un quiz que ya rindió
 * cambia por una corrección posterior del Profesor.
 */
class NotaQuizModificada extends Notification
{
    public function __construct(protected Quiz $quiz, protected float $notaAnterior, protected float $notaNueva) {}

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
            'titulo' => 'Nota de quiz actualizada',
            'mensaje' => "Tu nota en \"{$this->quiz->tema}\" cambió de {$this->notaAnterior} a {$this->notaNueva} por una corrección del profesor.",
            'url' => route('cursos.quizzes.show', [$this->quiz->curso_id, $this->quiz->id]),
        ];
    }
}
