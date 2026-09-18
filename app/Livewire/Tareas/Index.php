<?php

namespace App\Livewire\Tareas;

use App\Models\Curso;
use Illuminate\Support\Facades\Gate;
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

    public function render()
    {
        $usuario = auth()->user();

        $tareas = $this->curso->tareas()
            ->with(['modulo', 'entregas' => fn ($q) => $usuario->hasRole('estudiante')
                ? $q->where('estudiante_id', $usuario->id)
                : $q,
            ])
            ->orderBy('fecha_entrega')
            ->get();

        return view('livewire.tareas.index', ['tareas' => $tareas]);
    }
}
