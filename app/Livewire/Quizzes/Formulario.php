<?php

namespace App\Livewire\Quizzes;

use App\Models\Curso;
use App\Models\Quiz;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulario extends Component
{
    public Curso $curso;

    public ?Quiz $quiz = null;

    public string $tema = '';

    public ?int $modulo_id = null;

    /** @var array<int, array{enunciado: string, opciones: array<int, string>, respuesta_correcta: int}> */
    public array $preguntas = [];

    public bool $visible = true;

    public function mount(Curso $curso, ?Quiz $quiz = null): void
    {
        $this->curso = $curso;

        if ($quiz?->exists) {
            Gate::authorize('gestionar', $quiz);

            $this->quiz = $quiz;
            $this->tema = $quiz->tema;
            $this->modulo_id = $quiz->modulo_id;
            $this->preguntas = $quiz->preguntas;
            $this->visible = $quiz->visible;
        } else {
            Gate::authorize('crear', [Quiz::class, $curso]);
            $this->agregarPregunta();
        }
    }

    public function agregarPregunta(): void
    {
        $this->preguntas[] = [
            'enunciado' => '',
            'opciones' => ['', ''],
            'respuesta_correcta' => 0,
        ];
    }

    public function eliminarPregunta(int $indice): void
    {
        unset($this->preguntas[$indice]);
        $this->preguntas = array_values($this->preguntas);
    }

    public function agregarOpcion(int $indicePregunta): void
    {
        $this->preguntas[$indicePregunta]['opciones'][] = '';
    }

    public function eliminarOpcion(int $indicePregunta, int $indiceOpcion): void
    {
        unset($this->preguntas[$indicePregunta]['opciones'][$indiceOpcion]);
        $this->preguntas[$indicePregunta]['opciones'] = array_values($this->preguntas[$indicePregunta]['opciones']);

        if ($this->preguntas[$indicePregunta]['respuesta_correcta'] >= count($this->preguntas[$indicePregunta]['opciones'])) {
            $this->preguntas[$indicePregunta]['respuesta_correcta'] = 0;
        }
    }

    public function guardar(): void
    {
        $validado = $this->validate([
            'tema' => ['required', 'string', 'max:255'],
            'modulo_id' => ['required', 'exists:modulos,id'],
            'preguntas' => ['required', 'array', 'min:1'],
            'preguntas.*.enunciado' => ['required', 'string', 'max:500'],
            'preguntas.*.opciones' => ['required', 'array', 'min:2'],
            'preguntas.*.opciones.*' => ['required', 'string', 'max:255'],
            'preguntas.*.respuesta_correcta' => ['required', 'integer', 'min:0'],
            'visible' => ['boolean'],
        ]);

        if ($this->quiz) {
            $this->quiz->update($validado);
        } else {
            $this->curso->quizzes()->create($validado);
        }

        $this->redirect(route('cursos.quizzes.index', $this->curso), navigate: true);
    }

    public function render()
    {
        return view('livewire.quizzes.formulario', [
            'modulos' => $this->curso->modulos()->orderBy('orden')->get(),
        ]);
    }
}
