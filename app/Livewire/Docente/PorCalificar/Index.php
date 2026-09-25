<?php

namespace App\Livewire\Docente\PorCalificar;

use App\Models\Curso;
use App\Models\Entrega;
use App\Notifications\TareaCalificada;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public ?int $cursoFiltroId = null;
    public string $busqueda = '';

    // Modal de calificación rápida
    public bool $mostrarModalCalificar = false;
    public ?int $entregaSeleccionadaId = null;
    public ?Entrega $entregaSeleccionada = null;
    public string $nota = '';
    public string $feedback = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['profesor', 'supervisor']), 403);
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingCursoFiltroId(): void
    {
        $this->resetPage();
    }

    public function abrirCalificar(int $entregaId): void
    {
        $this->entregaSeleccionada = Entrega::with(['estudiante', 'tarea.curso'])->findOrFail($entregaId);
        $this->entregaSeleccionadaId = $entregaId;
        $this->nota = (string) ($this->entregaSeleccionada->nota ?? '');
        $this->feedback = $this->entregaSeleccionada->feedback ?? '';
        $this->mostrarModalCalificar = true;
    }

    public function guardarCalificacion(): void
    {
        $this->validate([
            'nota' => ['required', 'numeric', 'min:0', 'max:20'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $entrega = Entrega::with('tarea')->findOrFail($this->entregaSeleccionadaId);
        $tarea = $entrega->tarea;

        if ($tarea->esGrupal() && $entrega->grupo_id) {
            // Calificación grupal: propaga a todos los integrantes del grupo
            $entregasGrupo = Entrega::where('tarea_id', $tarea->id)
                ->where('grupo_id', $entrega->grupo_id)
                ->get();

            foreach ($entregasGrupo as $item) {
                $item->update([
                    'nota' => $this->nota,
                    'feedback' => $this->feedback,
                    'estado' => 'calificado',
                ]);

                if ($item->estudiante) {
                    $item->estudiante->notify(new TareaCalificada($item));
                }
            }

            session()->flash('estado', "Calificación grupal ({$this->nota}/20) propagada exitosamente a los integrantes del equipo.");
        } else {
            $entrega->update([
                'nota' => $this->nota,
                'feedback' => $this->feedback,
                'estado' => 'calificado',
            ]);

            if ($entrega->estudiante) {
                $entrega->estudiante->notify(new TareaCalificada($entrega));
            }

            session()->flash('estado', "Entrega de '{$entrega->estudiante?->name}' calificada con {$this->nota}/20.");
        }

        $this->mostrarModalCalificar = false;
        $this->reset(['entregaSeleccionadaId', 'entregaSeleccionada', 'nota', 'feedback']);
    }

    public function render()
    {
        $usuario = auth()->user();

        // Cursos asignados al docente o todos si es administrador
        $cursosQuery = $usuario->hasRole('supervisor')
            ? Curso::query()
            : $usuario->cursosComoProfesor();

        $cursos = $cursosQuery->orderBy('nombre')->get();
        $cursoIds = $cursos->pluck('id');

        // Entregas pendientes de calificar
        $entregasQuery = Entrega::query()
            ->whereHas('tarea', fn ($q) => $q->whereIn('curso_id', $cursoIds))
            ->where('estado', '!=', 'calificado')
            ->when($this->cursoFiltroId, fn ($q) => $q->whereHas('tarea', fn ($q2) => $q2->where('curso_id', $this->cursoFiltroId)))
            ->when($this->busqueda, fn ($q) => $q->where(function ($sub) {
                $sub->whereHas('estudiante', fn ($qEst) => $qEst->where('name', 'like', "%{$this->busqueda}%"))
                    ->orWhereHas('tarea', fn ($qTar) => $qTar->where('titulo', 'like', "%{$this->busqueda}%"));
            }))
            ->with(['estudiante', 'tarea.curso', 'grupo'])
            ->orderBy('created_at', 'asc'); // Más antiguas primero (cola FIFO de revisión)

        $totalPendientes = (clone $entregasQuery)->count();
        $conAtraso = (clone $entregasQuery)->where('created_at', '<', now()->subDays(3))->count();

        $entregas = $entregasQuery->paginate(10);

        return view('livewire.docente.por-calificar.index', [
            'entregas' => $entregas,
            'cursos' => $cursos,
            'totalPendientes' => $totalPendientes,
            'conAtraso' => $conAtraso,
        ]);
    }
}
