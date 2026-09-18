<?php

namespace App\Livewire\Quizzes;

use App\Models\Quiz;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Resolver extends Component
{
    public Quiz $quiz;

    /** @var array<int, int|null> */
    public array $respuestas = [];

    public function mount(Quiz $quiz): void
    {
        Gate::authorize('ver', $quiz);

        $this->quiz = $quiz;
        $this->respuestas = array_fill(0, count($quiz->preguntas), null);
    }

    public function getResultadoProperty()
    {
        return $this->quiz->resultadoDe(auth()->user());
    }

    public function enviar(): void
    {
        Gate::authorize('responder', $this->quiz);

        $this->validate([
            'respuestas' => ['required', 'array', 'size:'.count($this->quiz->preguntas)],
            'respuestas.*' => ['required', 'integer'],
        ], [
            'respuestas.*.required' => 'Responde todas las preguntas antes de enviar.',
        ]);

        $puntaje = $this->quiz->calcularPuntaje($this->respuestas);

        $this->quiz->resultados()->create([
            'estudiante_id' => auth()->id(),
            'respuestas' => $this->respuestas,
            'puntaje' => $puntaje,
        ]);
    }

    public function render()
    {
        return view('livewire.quizzes.resolver');
    }
}
