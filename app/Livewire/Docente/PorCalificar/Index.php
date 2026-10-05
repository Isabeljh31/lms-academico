<?php

namespace App\Livewire\Docente\PorCalificar;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Entrega;
use App\Notifications\TareaCalificada;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ERS RF-49 y RF-88: bandeja de entregas pendientes de calificar de todos
 * los cursos del profesor. Solo se listan y califican entregas de cursos
 * cuyo titular es el usuario (sin acceso a entregas de otros cursos).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public ?int $cursoFiltroId = null;

    public string $busqueda = '';

    public bool $mostrarModalCalificar = false;

    #[Locked]
    public ?int $entregaSeleccionadaId = null;

    public string $nota = '';

    public string $feedback = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['profesor', 'supervisor']), 403);
    }

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['busqueda', 'cursoFiltroId'], true)) {
            $this->resetPage();
        }
    }

    public function abrirCalificar(int $entregaId): void
    {
        $entrega = $this->entregasCalificables()->findOrFail($entregaId);
        Gate::authorize('calificar', $entrega);

        $this->resetErrorBag();
        $this->entregaSeleccionadaId = $entrega->id;
        $this->nota = $entrega->nota !== null ? (string) (float) $entrega->nota : '';
        $this->feedback = $entrega->feedback ?? '';
        $this->mostrarModalCalificar = true;
    }

    public function guardarCalificacion(): void
    {
        $entrega = $this->entregasCalificables()->with('tarea.curso')->findOrFail($this->entregaSeleccionadaId);
        Gate::authorize('calificar', $entrega);

        $this->validate([
            'nota' => ['required', 'numeric', 'min:0', 'max:20'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ], [
            'nota.required' => 'Ingresa la nota (0 a 20).',
        ]);

        $nota = round((float) $this->nota, 2);
        $miembros = $entrega->grupo_id
            ? Entrega::where('tarea_id', $entrega->tarea_id)->where('grupo_id', $entrega->grupo_id)->with('estudiante')->get()
            : collect([$entrega]);

        foreach ($miembros as $item) {
            $anterior = $item->nota;
            $item->update(['nota' => $nota, 'feedback' => $this->feedback ?: null, 'estado' => 'calificado']);

            if ($anterior !== null && (float) $anterior !== $nota) {
                BitacoraAuditoria::registrarCambioNota(
                    ejecutor: auth()->user(),
                    curso: $entrega->tarea->curso,
                    estudiante: $item->estudiante,
                    entidad: 'entrega_tarea',
                    entidadId: $item->id,
                    valorAnterior: $anterior,
                    valorNuevo: $nota,
                    motivo: 'Recalificación desde la bandeja Por calificar.'
                );
            }

            $item->estudiante?->notify(new TareaCalificada($item));
        }

        session()->flash('estado', $entrega->grupo_id
            ? "Calificación {$nota}/20 aplicada a todo el grupo."
            : "Entrega de {$entrega->estudiante?->name} calificada con {$nota}/20.");

        $this->mostrarModalCalificar = false;
        $this->reset(['entregaSeleccionadaId', 'nota', 'feedback']);
    }

    /**
     * Entregas de los cursos que dicta el usuario (titular o adicional) y que no están archivados.
     */
    protected function entregasCalificables(): Builder
    {
        return Entrega::query()->whereHas('tarea.curso', fn ($q) => $q
            ->dictadosPor(auth()->id())
            ->where('estado', '!=', 'archivado'));
    }

    public function render()
    {
        $cursos = Curso::dictadosPor(auth()->id())->where('estado', '!=', 'archivado')->orderBy('nombre')->get();

        $pendientes = $this->entregasCalificables()
            ->whereIn('estado', ['entregado'])
            ->when($this->cursoFiltroId, fn ($q) => $q->whereHas('tarea', fn ($t) => $t->where('curso_id', $this->cursoFiltroId)))
            ->when($this->busqueda, fn ($q) => $q->where(fn ($sub) => $sub
                ->whereHas('estudiante', fn ($e) => $e->where('name', 'like', "%{$this->busqueda}%"))
                ->orWhereHas('tarea', fn ($t) => $t->where('titulo', 'like', "%{$this->busqueda}%"))
            ));

        $totalPendientes = (clone $pendientes)->count();
        $conAtraso = (clone $pendientes)->where('updated_at', '<', now()->subDays(3))->count();

        return view('livewire.docente.por-calificar.index', [
            'entregas' => $pendientes->with(['estudiante', 'tarea.curso', 'grupo'])->orderBy('updated_at')->paginate(10),
            'cursos' => $cursos,
            'totalPendientes' => $totalPendientes,
            'conAtraso' => $conAtraso,
            'entregaSeleccionada' => $this->entregaSeleccionadaId
                ? $this->entregasCalificables()->with(['estudiante', 'tarea', 'grupo'])->find($this->entregaSeleccionadaId)
                : null,
        ])->title('Por calificar — LearnUp');
    }
}
