<?php

namespace App\Livewire\Compartido\Debates;

use App\Models\Curso;
use App\Models\Debate;
use App\Models\DebateComentario;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Conversación de un debate: comentarios principales paginados y sus
 * respuestas (un solo nivel). Cada autor edita o elimina lo suyo mientras el
 * debate esté abierto; el Profesor del curso puede eliminar cualquier
 * comentario (moderación) y cerrar o reabrir el debate. El Supervisor solo
 * lee. Permisos en DebatePolicy; cada acción busca el comentario dentro de
 * este debate (protección IDOR).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use WithPagination;

    /** Comentarios principales por página. */
    public const POR_PAGINA = 10;

    #[Locked]
    public Curso $curso;

    #[Locked]
    public Debate $debate;

    public string $nuevoComentario = '';

    #[Locked]
    public ?int $respondiendoA = null;

    public string $respuesta = '';

    #[Locked]
    public ?int $editandoId = null;

    public string $edicion = '';

    public function mount(Curso $curso, Debate $debate): void
    {
        Gate::authorize('ver', $debate);
        $this->curso = $curso;
        $this->debate = $debate;
    }

    public function comentar(): void
    {
        Gate::authorize('comentar', $this->debate);

        $this->validate(['nuevoComentario' => $this->reglaContenido()], $this->mensajes('nuevoComentario'));

        $this->debate->comentarios()->create([
            'user_id' => auth()->id(),
            'contenido' => $this->nuevoComentario,
        ]);

        $this->reset('nuevoComentario');
        // El comentario nuevo va al final: se muestra la última página.
        $this->setPage((int) max(1, ceil($this->debate->comentariosPrincipales()->count() / self::POR_PAGINA)));
    }

    public function responder(int $id): void
    {
        Gate::authorize('comentar', $this->debate);

        $this->respondiendoA = $this->comentarioPrincipal($id)->id;
        $this->reset('respuesta');
        $this->resetErrorBag();
    }

    public function cancelarRespuesta(): void
    {
        $this->reset(['respondiendoA', 'respuesta']);
    }

    public function enviarRespuesta(): void
    {
        Gate::authorize('comentar', $this->debate);
        abort_if($this->respondiendoA === null, 404);

        $padre = $this->comentarioPrincipal($this->respondiendoA);

        $this->validate(['respuesta' => $this->reglaContenido()], $this->mensajes('respuesta'));

        $this->debate->comentarios()->create([
            'user_id' => auth()->id(),
            'parent_id' => $padre->id,
            'contenido' => $this->respuesta,
        ]);

        $this->reset(['respondiendoA', 'respuesta']);
    }

    public function editar(int $id): void
    {
        $comentario = $this->comentario($id);
        Gate::authorize('editarComentario', $comentario);

        $this->editandoId = $comentario->id;
        $this->edicion = $comentario->contenido;
        $this->resetErrorBag();
    }

    public function cancelarEdicion(): void
    {
        $this->reset(['editandoId', 'edicion']);
    }

    public function guardarEdicion(): void
    {
        abort_if($this->editandoId === null, 404);

        $comentario = $this->comentario($this->editandoId);
        Gate::authorize('editarComentario', $comentario);

        $this->validate(['edicion' => $this->reglaContenido()], $this->mensajes('edicion'));

        $comentario->update(['contenido' => $this->edicion, 'editado_en' => now()]);
        $this->reset(['editandoId', 'edicion']);
    }

    public function eliminar(int $id): void
    {
        $comentario = $this->comentario($id);
        Gate::authorize('eliminarComentario', $comentario);

        // Las respuestas también las borra la clave foránea; se borran aquí por si la base no la aplica.
        $comentario->respuestas()->delete();
        $comentario->delete();

        if ($this->editandoId === $id) {
            $this->reset(['editandoId', 'edicion']);
        }

        if ($this->respondiendoA === $id) {
            $this->reset(['respondiendoA', 'respuesta']);
        }

        session()->flash('estado', 'Comentario eliminado.');
    }

    public function alternarCierre(): void
    {
        Gate::authorize('gestionar', $this->debate);

        $this->debate->update(['cerrado_en' => $this->debate->estaCerrado() ? null : now()]);
        $this->reset(['respondiendoA', 'respuesta', 'editandoId', 'edicion', 'nuevoComentario']);
        session()->flash('estado', $this->debate->estaCerrado() ? 'Debate cerrado: queda en solo lectura.' : 'Debate reabierto.');
    }

    /**
     * @return array<int, string>
     */
    protected function reglaContenido(): array
    {
        return ['required', 'string', 'max:'.Debate::MAX_COMENTARIO];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajes(string $campo): array
    {
        return [
            "{$campo}.required" => 'Escribe tu comentario.',
            "{$campo}.max" => 'El comentario admite como máximo '.Debate::MAX_COMENTARIO.' caracteres.',
        ];
    }

    protected function comentario(int $id): DebateComentario
    {
        return $this->debate->comentarios()->findOrFail($id);
    }

    protected function comentarioPrincipal(int $id): DebateComentario
    {
        return $this->debate->comentariosPrincipales()->findOrFail($id);
    }

    public function render()
    {
        $comentarios = $this->debate->comentariosPrincipales()
            ->with(['autor', 'respuestas' => fn ($query) => $query->oldest()->with('autor')])
            ->oldest()
            ->paginate(self::POR_PAGINA);

        return view('livewire.compartido.debates.show', [
            'comentarios' => $comentarios,
            'docentes' => $this->curso->docentes()->pluck('id')->all(),
            // Permisos calculados una vez por render; cada acción vuelve a autorizar.
            'puedeComentar' => Gate::allows('comentar', $this->debate),
            'puedeModerar' => Gate::allows('gestionar', $this->debate),
            'totalComentarios' => $this->debate->comentarios()->count(),
        ])->title("{$this->debate->titulo} — Debates");
    }
}
