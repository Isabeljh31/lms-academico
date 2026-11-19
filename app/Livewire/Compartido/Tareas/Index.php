<?php

namespace App\Livewire\Compartido\Tareas;

use App\Models\Curso;
use App\Models\Tarea;
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

    /** Vista de docente (Profesor del curso o Supervisor en solo lectura). */
    public function getEsVistaDocenteProperty(): bool
    {
        return Gate::allows('supervisar', $this->curso);
    }

    /**
     * Publica u oculta la tarea (RF-37). Al pasar a visible por primera vez
     * se notifica a los matriculados (RF-84).
     */
    public function alternarVisibilidad(int $tareaId): void
    {
        $tarea = $this->curso->tareas()->findOrFail($tareaId);
        Gate::authorize('gestionar', $tarea);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado.');

        $eraVisible = $tarea->esVisibleParaEstudiante();

        $tarea->update($tarea->visible
            ? ['visible' => false]
            : ['visible' => true, 'estado_contenido' => 'publicado', 'publicar_en' => null]);

        if (! $eraVisible && $tarea->fresh()->esVisibleParaEstudiante()) {
            Notification::send($this->estudiantesActivos(), new NuevaTareaAsignada($tarea));
        }
    }

    public function eliminar(int $tareaId): void
    {
        $tarea = $this->curso->tareas()->findOrFail($tareaId);
        Gate::authorize('eliminar', $tarea);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado.');

        $tarea->delete();
        session()->flash('estado', "Tarea \"{$tarea->titulo}\" eliminada.");
    }

    protected function estudiantesActivos()
    {
        return $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter();
    }

    public function render()
    {
        $usuario = auth()->user();
        $esGestor = $this->esVistaDocente;

        $tareas = $this->curso->tareas()
            ->with(['modulo', 'grupoAsignado', 'entregas' => fn ($q) => $esGestor ? $q : $q->where('estudiante_id', $usuario->id)])
            ->when(! $esGestor, fn ($q) => $q->visiblesParaEstudiante())
            ->orderBy('fecha_entrega')
            ->get()
            ->when(! $esGestor, fn ($tareas) => $tareas->filter(fn (Tarea $tarea) => Gate::allows('ver', $tarea))->values());

        return view('livewire.compartido.tareas.index', ['tareas' => $tareas])
            ->title("Tareas — {$this->curso->nombre}");
    }
}
