<?php

namespace App\Livewire\Quizzes;

use App\Models\Quiz;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Módulo 4 del documento del proyecto: "el profesor revisa qué preguntas
 * o temas tuvieron más errores". Solo lectura, no crea tablas nuevas:
 * calcula todo a partir de `preguntas` y `resultados_quiz` ya existentes
 * (ver Quiz::estadisticasPorPregunta).
 */
#[Layout('layouts.app')]
class Estadisticas extends Component
{
    public Quiz $quiz;

    public function mount(Quiz $quiz): void
    {
        Gate::authorize('gestionar', $quiz);

        $this->quiz = $quiz;
    }

    public function render()
    {
        $this->quiz->loadMissing(['resultados', 'curso', 'modulo']);

        return view('livewire.quizzes.estadisticas', [
            'preguntas' => $this->quiz->estadisticasPorPregunta(),
            'totalIntentos' => $this->quiz->resultados->count(),
            'promedioGeneral' => $this->quiz->resultados->isNotEmpty()
                ? round((float) $this->quiz->resultados->avg('puntaje'), 2)
                : null,
        ]);
    }
}
