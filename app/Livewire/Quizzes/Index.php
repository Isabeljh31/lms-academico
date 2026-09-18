<?php

namespace App\Livewire\Quizzes;

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

        $quizzes = $this->curso->quizzes()
            ->with(['modulo', 'resultados' => fn ($q) => $usuario->hasRole('estudiante')
                ? $q->where('estudiante_id', $usuario->id)
                : $q,
            ])
            ->orderBy('created_at')
            ->get();

        return view('livewire.quizzes.index', ['quizzes' => $quizzes]);
    }
}
