<?php

namespace App\Livewire\Tareas;

use App\Models\Entrega;
use App\Models\Grupo;
use App\Models\Tarea;
use App\Notifications\TareaCalificada;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Detalle extends Component
{
    use WithFileUploads;

    public Tarea $tarea;

    public ?UploadedFile $archivo = null;

    public string $comentario = '';

    /** @var array<int, string> */
    public array $notas = [];

    /** @var array<int, string> */
    public array $feedbacks = [];

    public function mount(Tarea $tarea): void
    {
        Gate::authorize('ver', $tarea);

        $this->tarea = $tarea;
    }

    public function getPuedeGestionarProperty(): bool
    {
        return Gate::allows('gestionar', $this->tarea);
    }

    public function getMiEntregaProperty(): ?Entrega
    {
        return $this->tarea->entregaDe(auth()->user());
    }

    public function getMiGrupoProperty(): ?Grupo
    {
        if (! $this->tarea->esGrupal()) {
            return null;
        }

        return auth()->user()->grupoEnCurso($this->tarea->curso);
    }

    public function entregar(SubidorArchivos $subidor): void
    {
        Gate::authorize('crear', [Entrega::class, $this->tarea]);

        if ($this->miEntrega) {
            $this->addError('archivo', 'Ya entregaste esta tarea.');

            return;
        }

        if ($this->tarea->esGrupal() && ! $this->miGrupo) {
            $this->addError('archivo', 'Todavía no perteneces a ningún grupo en este curso. Pide a tu profesor que te agregue a uno antes de entregar.');

            return;
        }

        $this->validate([
            'archivo' => ['required', 'file', 'max:20480'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        $url = $subidor->subir($this->archivo, 'entregas');

        if ($this->tarea->esGrupal()) {
            // Una entrega grupal crea/actualiza una fila por cada integrante
            // del grupo (mismo archivo), así el resto de la plataforma
            // (progreso, badges, notificaciones) sigue leyendo entregas por
            // estudiante_id exactamente igual que antes.
            foreach ($this->miGrupo->estudiantes as $miembro) {
                $this->tarea->entregas()->updateOrCreate(
                    ['estudiante_id' => $miembro->id],
                    [
                        'grupo_id' => $this->miGrupo->id,
                        'archivo_url' => $url,
                        'comentario' => $this->comentario,
                        'estado' => 'entregado',
                    ]
                );
            }
        } else {
            $this->tarea->entregas()->create([
                'estudiante_id' => auth()->id(),
                'archivo_url' => $url,
                'comentario' => $this->comentario,
                'estado' => 'entregado',
            ]);
        }

        $this->archivo = null;
        $this->comentario = '';
    }

    public function calificar(int $entregaId): void
    {
        $entrega = Entrega::findOrFail($entregaId);

        Gate::authorize('calificar', $entrega);

        $nota = $this->notas[$entregaId] ?? null;

        $this->validate([
            "notas.$entregaId" => ['required', 'numeric', 'min:0', 'max:20'],
            "feedbacks.$entregaId" => ['nullable', 'string', 'max:1000'],
        ]);

        $feedback = $this->feedbacks[$entregaId] ?? null;

        // Calificar una entrega grupal aplica la misma nota/feedback a todos
        // los integrantes del grupo, en una sola acción del profesor.
        $entregasACalificar = $entrega->grupo_id
            ? Entrega::where('tarea_id', $entrega->tarea_id)->where('grupo_id', $entrega->grupo_id)->with('estudiante')->get()
            : collect([$entrega]);

        foreach ($entregasACalificar as $entregaMiembro) {
            $entregaMiembro->update(['nota' => $nota, 'feedback' => $feedback, 'estado' => 'calificado']);
            $entregaMiembro->estudiante->notify(new TareaCalificada($entregaMiembro));
        }

        session()->flash('estado', $entrega->grupo_id
            ? 'Calificación guardada para todo el grupo ('.$entrega->grupo->nombre.').'
            : 'Calificación guardada para '.$entrega->estudiante->name.'.');
    }

    public function render()
    {
        $entregas = $this->puedeGestionar
            ? $this->tarea->entregas()->with('estudiante', 'grupo')->get()
            : collect();

        foreach ($entregas as $entrega) {
            $this->notas[$entrega->id] ??= (string) ($entrega->nota ?? '');
            $this->feedbacks[$entrega->id] ??= $entrega->feedback ?? '';
        }

        return view('livewire.tareas.detalle', ['entregas' => $entregas]);
    }
}
