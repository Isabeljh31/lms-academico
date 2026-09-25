<?php

namespace App\Livewire\Tareas;

use App\Models\Curso;
use App\Models\User;
use App\Notifications\NuevaTareaAsignada;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public Curso $curso;

    public function mount(Curso $curso): void
    {
        Gate::authorize('ver', $curso);

        $this->curso = $curso;
    }

    public function getPuedeGestionarProperty(): bool
    {
        return Gate::allows('gestionar', $this->curso);
    }

    /**
     * Publica u oculta la tarea. Al pasar de oculta a visible por primera
     * vez, notifica a los inscritos (mismo aviso que si se hubiera creado
     * ya visible) — así el profesor puede preparar tareas con anticipación
     * y "activarlas" en medio de la clase sin tener que recrearlas.
     */
    public function alternarVisibilidad(int $tareaId): void
    {
        $tarea = $this->curso->tareas()->findOrFail($tareaId);
        Gate::authorize('gestionar', $tarea);

        $eraVisible = $tarea->visible;
        $tarea->update(['visible' => ! $tarea->visible]);

        if ($tarea->visible && ! $eraVisible) {
            $estudiantes = User::whereIn('id', $this->curso->inscripciones()->pluck('estudiante_id'))->get();
            Notification::send($estudiantes, new NuevaTareaAsignada($tarea));
        }
    }

    public function eliminar(int $tareaId): void
    {
        $tarea = $this->curso->tareas()->findOrFail($tareaId);
        Gate::authorize('eliminar', $tarea);

        $tarea->delete();
    }

    public function render()
    {
        $usuario = auth()->user();

        $tareas = $this->curso->tareas()
            ->with(['modulo', 'entregas' => fn ($q) => $usuario->hasRole('estudiante')
                ? $q->where('estudiante_id', $usuario->id)
                : $q,
            ])
            ->when($usuario->hasRole('estudiante'), fn ($q) => $q->where('visible', true))
            ->orderBy('fecha_entrega')
            ->get();

        return view('livewire.tareas.index', ['tareas' => $tareas]);
    }
}
