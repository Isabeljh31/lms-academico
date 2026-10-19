<?php

namespace App\Livewire\BotSugerencias;

use App\Models\PropuestaBot;
use App\Models\Tarea;
use App\Services\Bot\PropuestaBotService;
use App\Services\Calendario\ColoresCursos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Bandeja de aprobación (human-in-the-loop) del Módulo 7: el estudiante
 * decide sobre las propuestas de redistribución de su propio plan de
 * estudio. Aceptar/ajustar ejecuta el cambio real vía
 * PropuestaBotService::ejecutarPropuestaAprobada(); rechazar no toca el
 * calendario.
 *
 * Presentación: las pendientes van primero y destacadas (requieren acción);
 * las resueltas se listan aparte, discretas. Cada propuesta lleva el color
 * de su curso (el mismo del Calendario, ColoresCursos) y un enlace al
 * Calendario centrado en la fecha que referencia.
 */
#[Layout('layouts.app')]
class Bandeja extends Component
{
    /** @var array<int, string> propuesta_id => fecha del recordatorio (Y-m-d\TH:i) */
    public array $fechasAjustadas = [];

    public function mount(): void
    {
        // El selector muestra desde el inicio la fecha que propone el bot; el estudiante puede cambiarla.
        $this->propuestas()->where('estado', 'pendiente')->each(function (PropuestaBot $propuesta) {
            $this->fechasAjustadas[$propuesta->id] = $this->fechaPropuesta($propuesta)->format('Y-m-d\TH:i');
        });
    }

    public function aceptar(int $propuestaId, PropuestaBotService $servicio): void
    {
        $propuesta = PropuestaBot::where('usuario_id', auth()->id())->findOrFail($propuestaId);

        Gate::authorize('responder', $propuesta);

        $this->validate(
            ["fechasAjustadas.{$propuestaId}" => ['nullable', 'date']],
            ["fechasAjustadas.{$propuestaId}.date" => 'Elige una fecha y hora válidas para el recordatorio.'],
        );

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

        $evento = $servicio->ejecutarPropuestaAprobada($propuesta);

        // Solo para enlazar la propuesta con el recordatorio creado ("Ver en calendario").
        $propuesta->update(['datos_propuesta' => [...$propuesta->datos_propuesta, 'evento_calendario_id' => $evento->id]]);

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

    /**
     * @return Builder<PropuestaBot>
     */
    protected function propuestas()
    {
        return PropuestaBot::where('usuario_id', auth()->id())
            ->whereIn('tipo', array_keys(PropuestaBot::TIPOS));
    }

    /** Fecha del recordatorio tal como la propuso el bot (hora local de la plataforma, sin convertir). */
    protected function fechaPropuesta(PropuestaBot $propuesta): Carbon
    {
        return Carbon::parse(substr((string) ($propuesta->datos_propuesta['nueva_fecha_recordatorio'] ?? now()), 0, 16));
    }

    /**
     * Datos que necesita cada tarjeta.
     *
     * @param  Collection<int, Tarea>  $tareas
     * @param  array<int, string>  $colores
     * @return array{propuesta: PropuestaBot, presentacion: array{etiqueta: string, icono: string, accion: string}, color: string, curso: string, tarea: ?Tarea, tareaTitulo: string, recordatorio: Carbon, enlaceCalendario: string}
     */
    protected function tarjeta(PropuestaBot $propuesta, Collection $tareas, array $colores): array
    {
        $datos = $propuesta->datos_propuesta;
        $tarea = $tareas->get($datos['tarea_id'] ?? 0);
        $recordatorio = $this->fechaPropuesta($propuesta);

        // Pendiente o aceptada: el día del recordatorio. Rechazada: el vencimiento de la tarea.
        [$fecha, $evento] = match (true) {
            $propuesta->estado === 'aprobado' && isset($datos['evento_calendario_id']) => [$recordatorio, 'evento-'.$datos['evento_calendario_id']],
            $propuesta->estado === 'rechazado' && $tarea?->fecha_entrega => [$tarea->fecha_entrega, 'tarea-'.$tarea->id],
            default => [$recordatorio, $tarea ? 'tarea-'.$tarea->id : null],
        };

        return [
            'propuesta' => $propuesta,
            'presentacion' => $propuesta->presentacion(),
            'color' => $colores[$datos['curso_id'] ?? 0] ?? ColoresCursos::NEUTRO,
            'curso' => $datos['curso_nombre'] ?? 'Curso',
            'tarea' => $tarea,
            'tareaTitulo' => $datos['tarea_titulo'] ?? '',
            'recordatorio' => $recordatorio,
            'enlaceCalendario' => route('evento-calendario.index', array_filter([
                'fecha' => $fecha->format('Y-m-d'),
                'evento' => $evento,
                'desde' => 'propuestas',
            ])),
        ];
    }

    public function render()
    {
        $propuestas = $this->propuestas()->orderByDesc('created_at')->get();

        $tareas = Tarea::whereIn('id', $propuestas->pluck('datos_propuesta.tarea_id')->filter())->get()->keyBy('id');
        $colores = app(ColoresCursos::class)->paraUsuario(auth()->user());
        $tarjetas = $propuestas->map(fn (PropuestaBot $propuesta) => $this->tarjeta($propuesta, $tareas, $colores));

        return view('livewire.bot-sugerencias.bandeja', [
            'pendientes' => $tarjetas->filter(fn (array $t) => $t['propuesta']->estado === 'pendiente')->values(),
            'resueltas' => $tarjetas->reject(fn (array $t) => $t['propuesta']->estado === 'pendiente')
                ->sortByDesc(fn (array $t) => $t['propuesta']->fecha_respuesta)->values(),
        ])->title('Propuestas del asistente — LearnUp');
    }
}
