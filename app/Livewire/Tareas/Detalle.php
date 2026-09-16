<?php

namespace App\Livewire\Tareas;

use App\Models\Entrega;
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

    public function entregar(SubidorArchivos $subidor): void
    {
        Gate::authorize('crear', [Entrega::class, $this->tarea]);

        if ($this->miEntrega) {
            $this->addError('archivo', 'Ya entregaste esta tarea.');

            return;
        }

        $this->validate([
            'archivo' => ['required', 'file', 'max:20480'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        $url = $subidor->subir($this->archivo, 'entregas');

        $this->tarea->entregas()->create([
            'estudiante_id' => auth()->id(),
            'archivo_url' => $url,
            'comentario' => $this->comentario,
            'estado' => 'entregado',
        ]);

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

        $entrega->update([
            'nota' => $nota,
            'feedback' => $this->feedbacks[$entregaId] ?? null,
            'estado' => 'calificado',
        ]);

        $entrega->estudiante->notify(new TareaCalificada($entrega));

        session()->flash('estado', 'Calificación guardada para '.$entrega->estudiante->name.'.');
    }

    public function render()
    {
        $entregas = $this->puedeGestionar
            ? $this->tarea->entregas()->with('estudiante')->get()
            : collect();

        foreach ($entregas as $entrega) {
            $this->notas[$entrega->id] ??= (string) ($entrega->nota ?? '');
            $this->feedbacks[$entrega->id] ??= $entrega->feedback ?? '';
        }

        return view('livewire.tareas.detalle', ['entregas' => $entregas]);
    }
}
