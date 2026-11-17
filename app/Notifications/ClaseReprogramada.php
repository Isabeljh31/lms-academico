<?php

namespace App\Notifications;

use App\Models\SolicitudCambioClase;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los estudiantes matriculados: una clase cambió de fecha, hora,
 * salón o modalidad, o volvió a su horario original al revertirse la
 * reprogramación. Nunca incluye el motivo ni el sustento del Profesor.
 */
class ClaseReprogramada extends Notification
{
    public function __construct(protected SolicitudCambioClase $solicitud, protected bool $revertida = false) {}

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
        $curso = $this->solicitud->curso;
        $original = $this->solicitud->fecha_original->format('d/m/Y H:i');
        $nueva = $this->solicitud->fecha_nueva->format('d/m/Y H:i');

        if ($this->revertida) {
            $donde = match (true) {
                $this->solicitud->modalidad_original === 'remoto' => ', en modalidad remota',
                $this->solicitud->salonOriginal !== null => ', en el aula '.$this->solicitud->salonOriginal->codigo,
                default => '',
            };

            return [
                'titulo' => 'Clase vuelve a su horario',
                'mensaje' => "La clase de {$curso->nombre} que se había movido al {$nueva} vuelve a su horario original: el {$original}{$donde}.",
                'url' => route('cursos.show', $curso),
            ];
        }

        $donde = $this->solicitud->modalidad_nueva === 'remoto'
            ? 'en modalidad remota'
            : 'en el aula '.$this->solicitud->aula_nueva;

        return [
            'titulo' => 'Clase reprogramada',
            'mensaje' => "La clase del {$original} de {$curso->nombre} ahora es el {$nueva}, {$donde}.",
            'url' => route('cursos.show', $curso),
        ];
    }
}
