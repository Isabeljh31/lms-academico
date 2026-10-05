<?php

namespace App\Notifications;

use App\Models\Curso;
use App\Models\RubroEvaluacion;
use Illuminate\Notifications\Notification;

/**
 * ERS RF-84: aviso de nueva calificación (o ajuste) en un rubro de evaluación.
 */
class NotaRubroRegistrada extends Notification
{
    public function __construct(protected Curso $curso, protected RubroEvaluacion $rubro, protected bool $esAjuste = false) {}

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
            'titulo' => $this->esAjuste ? 'Nota actualizada' : 'Nueva calificación',
            'mensaje' => ($this->esAjuste ? 'Se actualizó' : 'Se registró')." tu nota de \"{$this->rubro->nombre}\" en {$this->curso->nombre}.",
            'url' => route('cursos.boleta', $this->curso),
        ];
    }
}
