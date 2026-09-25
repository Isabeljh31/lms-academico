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

    public function alternarVisibilidad(int $quizId): void
    {
        $quiz = $this->curso->quizzes()->findOrFail($quizId);
        Gate::authorize('gestionar', $quiz);

        $quiz->update(['visible' => ! $quiz->visible]);
    }

    public function eliminar(int $quizId): void
    {
        $quiz = $this->curso->quizzes()->findOrFail($quizId);
        Gate::authorize('eliminar', $quiz);

        $quiz->delete();
    }

    public function render()
    {
        $usuario = auth()->user();

        $quizzes = $this->curso->quizzes()
            ->with(['modulo', 'resultados' => fn ($q) => $usuario->hasRole('estudiante')
                ? $q->where('estudiante_id', $usuario->id)
                : $q,
            ])
            ->when($usuario->hasRole('estudiante'), fn ($q) => $q->where('visible', true))
            ->orderBy('created_at')
            ->get();

        return view('livewire.quizzes.index', ['quizzes' => $quizzes]);
    }
}
