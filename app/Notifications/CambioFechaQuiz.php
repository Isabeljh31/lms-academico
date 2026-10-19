<?php

namespace App\Notifications;

use App\Models\Quiz;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * ERS RF-84: aviso de cambio en la fecha límite de un quiz ya publicado.
 */
class CambioFechaQuiz extends Notification
{
    public function __construct(protected Quiz $quiz, protected ?Carbon $fechaAnterior) {}

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
        $nueva = $this->quiz->fecha_limite?->formatoFechaHora() ?? 'sin fecha límite';
        $anterior = $this->fechaAnterior?->formatoFechaHora() ?? 'sin fecha límite';

        return [
            'titulo' => 'Cambio de fecha',
            'mensaje' => "El quiz \"{$this->quiz->tema}\" ahora cierra: {$nueva} (antes {$anterior}).",
            'url' => route('cursos.quizzes.show', [$this->quiz->curso_id, $this->quiz->id]),
        ];
    }
}
