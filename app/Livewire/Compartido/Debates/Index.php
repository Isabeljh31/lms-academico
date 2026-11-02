<?php

namespace App\Livewire\Compartido\Debates;

use App\Models\Curso;
use App\Models\Debate;
use App\Notifications\NuevoDebatePublicado;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pestaña "Debates" del curso (extensión fuera del ERS, inspirada en la
 * pestaña Debates de Blackboard). El Profesor del curso crea, edita, cierra
 * o reabre y elimina debates; estudiantes matriculados y Supervisor ven la
 * lista (el estudiante, solo los publicados). Permisos en DebatePolicy.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public Curso $curso;

    public bool $mostrarFormulario = false;

    #[Locked]
    public ?int $debateEditId = null;

    public string $titulo = '';

    public string $consigna = '';

    public string $estado = 'borrador';

    public function mount(Curso $curso): void
    {
        Gate::authorize('verLista', [Debate::class, $curso]);
        $this->curso = $curso;
    }

    public function getPuedeGestionarProperty(): bool
    {
        return Gate::allows('crear', [Debate::class, $this->curso]);
    }

    public function abrirNuevo(): void
    {
        Gate::authorize('crear', [Debate::class, $this->curso]);
        $this->resetErrorBag();
        $this->reset(['debateEditId', 'titulo', 'consigna', 'estado']);
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $debate = $this->debate($id);
        Gate::authorize('gestionar', $debate);

        $this->resetErrorBag();
        $this->debateEditId = $debate->id;
        $this->titulo = $debate->titulo;
        $this->consigna = $debate->consigna;
        $this->estado = $debate->estado;
        $this->mostrarFormulario = true;
    }

    public function guardar(): void
    {
        $debate = $this->debateEditId ? $this->debate($this->debateEditId) : null;

        if ($debate) {
            Gate::authorize('gestionar', $debate);
        } else {
            Gate::authorize('crear', [Debate::class, $this->curso]);
        }

        $this->validate([
            'titulo' => ['required', 'string', 'max:'.Debate::MAX_TITULO],
            'consigna' => ['required', 'string', 'max:10000'],
            'estado' => ['required', Rule::in(array_keys(Debate::ESTADOS))],
        ], [
            'titulo.max' => 'El título admite como máximo '.Debate::MAX_TITULO.' caracteres.',
        ]);

        $datos = ['titulo' => $this->titulo, 'consigna' => $this->consigna, 'estado' => $this->estado];

        if ($debate) {
            $debate->update($datos);
            $mensaje = 'Debate actualizado.';
        } else {
            $debate = $this->curso->debates()->create([...$datos, 'autor_id' => auth()->id()]);
            $mensaje = 'Debate creado.';
        }

        if ($this->notificarSiEsPrimeraPublicacion($debate)) {
            $mensaje .= ' Se notificó a los estudiantes.';
        }

        $this->mostrarFormulario = false;
        $this->reset(['debateEditId', 'titulo', 'consigna', 'estado']);
        session()->flash('estado', $mensaje);
    }

    public function alternarCierre(int $id): void
    {
        $debate = $this->debate($id);
        Gate::authorize('gestionar', $debate);

        $debate->update(['cerrado_en' => $debate->estaCerrado() ? null : now()]);
        session()->flash('estado', $debate->estaCerrado() ? 'Debate cerrado: queda en solo lectura.' : 'Debate reabierto.');
    }

    public function eliminar(int $id): void
    {
        $debate = $this->debate($id);
        Gate::authorize('gestionar', $debate);

        $debate->delete();
        session()->flash('estado', 'Debate eliminado.');
    }

    /**
     * Avisa a los estudiantes la primera vez que el debate se publica; si se
     * despublica y se vuelve a publicar no se repite el aviso.
     */
    protected function notificarSiEsPrimeraPublicacion(Debate $debate): bool
    {
        if (! $debate->estaPublicado() || $debate->publicado_en !== null) {
            return false;
        }

        $debate->update(['publicado_en' => now()]);

        if (! $this->curso->esVisibleParaEstudiantes()) {
            return false;
        }

        $estudiantes = $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter();
        Notification::send($estudiantes, new NuevoDebatePublicado($debate));

        return $estudiantes->isNotEmpty();
    }

    protected function debate(int $id): Debate
    {
        return $this->curso->debates()->findOrFail($id);
    }

    public function render()
    {
        $debates = $this->curso->debates()
            ->unless(Gate::allows('supervisar', $this->curso), fn ($query) => $query->publicados())
            ->withCount('comentarios')
            ->latest()
            ->paginate(10);

        return view('livewire.compartido.debates.index', [
            'debates' => $debates,
            'puedeGestionar' => $this->puedeGestionar,
        ])->title("Debates — {$this->curso->nombre}");
    }
}
