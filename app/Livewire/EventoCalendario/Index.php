<?php

namespace App\Livewire\EventoCalendario;

use App\Models\EventoCalendario;
use App\Models\Tarea;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public string $nuevoTitulo = '';

    public string $nuevaFecha = '';

    public string $nuevoTipo = 'bloque_estudio';

    public function crear(): void
    {
        $this->validate([
            'nuevoTitulo' => ['required', 'string', 'max:255'],
            'nuevaFecha' => ['required', 'date'],
            'nuevoTipo' => ['required', 'in:bloque_estudio,recordatorio,evento'],
        ]);

        $evento = auth()->user()->eventosCalendario()->create([
            'titulo' => $this->nuevoTitulo,
            'fecha' => $this->nuevaFecha,
            'tipo' => $this->nuevoTipo,
        ]);

        $this->reset(['nuevoTitulo', 'nuevaFecha']);
        $this->dispatch('evento-creado', evento: $this->payloadEvento($evento));
    }

    public function eliminar(int $eventoId): void
    {
        $evento = EventoCalendario::where('usuario_id', auth()->id())->findOrFail($eventoId);
        $evento->delete();

        $this->dispatch('evento-eliminado', id: 'evento-'.$eventoId);
    }

    protected function payloadEvento(EventoCalendario $e): array
    {
        return [
            'id' => 'evento-'.$e->id,
            'title' => $e->titulo,
            'start' => $e->fecha->toIso8601String(),
            // Tonos oscuros de la paleta (no los pasteles base) para que el
            // texto blanco del evento mantenga buen contraste también en
            // modo oscuro — ver ARQUITECTURA.md § pulido del rol estudiante.
            'backgroundColor' => match ($e->tipo) {
                'bloque_estudio' => '#3C7A75',
                'recordatorio' => '#B2604A',
                default => '#86715E',
            },
            'borderColor' => 'transparent',
            'textColor' => '#ffffff',
            'extendedProps' => ['tipo' => $e->tipo, 'propio' => true, 'idNumerico' => $e->id],
        ];
    }

    protected function tareasRelevantes()
    {
        $usuario = auth()->user();

        if ($usuario->hasRole('profesor')) {
            return Tarea::whereHas('curso', fn ($q) => $q->where('profesor_id', $usuario->id))->with('curso')->get();
        }

        if ($usuario->hasRole('estudiante')) {
            $cursoIds = $usuario->inscripciones()->pluck('curso_id');

            return Tarea::whereIn('curso_id', $cursoIds)->where('visible', true)->with('curso')->get();
        }

        return collect();
    }

    public function getEventosProperty(): array
    {
        $propios = auth()->user()->eventosCalendario()->get()->map(fn (EventoCalendario $e) => $this->payloadEvento($e));

        $tareas = $this->tareasRelevantes()->map(fn (Tarea $t) => [
            'id' => 'tarea-'.$t->id,
            'title' => $t->titulo.' ('.$t->curso->nombre.')',
            'start' => $t->fecha_entrega->toIso8601String(),
            'url' => route('cursos.tareas.show', [$t->curso_id, $t->id]),
            'backgroundColor' => '#B2604A',
            'borderColor' => 'transparent',
            'textColor' => '#ffffff',
            'extendedProps' => ['tipo' => 'tarea', 'propio' => false],
        ]);

        return $propios->concat($tareas)->values()->all();
    }

    public function render()
    {
        return view('livewire.evento-calendario.index');
    }
}
