<?php

namespace App\Livewire\BotSugerencias;

use App\Models\PropuestaBot;
use App\Services\Bot\PropuestaBotService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Bandeja de aprobación (human-in-the-loop) del Módulo 7: el estudiante
 * decide sobre las propuestas de redistribución de su propio plan de
 * estudio. Aceptar/ajustar ejecuta el cambio real vía
 * PropuestaBotService::ejecutarPropuestaAprobada(); rechazar no toca el
 * calendario.
 */
#[Layout('layouts.app')]
class Bandeja extends Component
{
    /** @var array<int, string> */
    public array $fechasAjustadas = [];

    public function aceptar(int $propuestaId, PropuestaBotService $servicio): void
    {
        $propuesta = PropuestaBot::where('usuario_id', auth()->id())->findOrFail($propuestaId);

        Gate::authorize('responder', $propuesta);

        if (! empty($this->fechasAjustadas[$propuestaId])) {
            $propuesta->datos_propuesta = [
                ...$propuesta->datos_propuesta,
                'nueva_fecha_recordatorio' => $this->fechasAjustadas[$propuestaId],
            ];
        }

        $propuesta->estado = 'aprobado';
        $propuesta->aprobado_por = auth()->id();
        $propuesta->fecha_respuesta = now();
        $propuesta->save();

        $servicio->ejecutarPropuestaAprobada($propuesta);

        session()->flash('estado', 'Propuesta aceptada: se agregó el recordatorio a tu calendario.');
    }

    public function rechazar(int $propuestaId): void
    {
        $propuesta = PropuestaBot::where('usuario_id', auth()->id())->findOrFail($propuestaId);

        Gate::authorize('responder', $propuesta);

        $propuesta->update([
            'estado' => 'rechazado',
            'aprobado_por' => auth()->id(),
            'fecha_respuesta' => now(),
        ]);

        session()->flash('estado', 'Propuesta rechazada. No se modificó tu calendario.');
    }

    public function render()
    {
        $propuestas = PropuestaBot::where('usuario_id', auth()->id())
            ->where('tipo', 'redistribucion_plan')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.bot-sugerencias.bandeja', ['propuestas' => $propuestas]);
    }
}
