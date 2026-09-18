<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\Models\Entrega;
use App\Models\ResultadoQuiz;
use App\Models\Tarea;
use App\Models\User;
use App\Notifications\TareaCalificada;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LibroCalificaciones extends Component
{
    public Curso $curso;

    // Modal para editar / calificar nota directa
    public bool $mostrarModalNota = false;
    public ?int $estudianteId = null;
    public ?int $tareaId = null;
    public ?User $estudianteSeleccionado = null;
    public ?Tarea $tareaSeleccionada = null;
    public string $nota = '';
    public string $feedback = '';

    public function mount(Curso $curso): void
    {
        Gate::authorize('gestionar', $curso);
        $this->curso = $curso;
    }

    public function abrirEdicionNota(int $estudianteId, int $tareaId): void
    {
        $this->estudianteId = $estudianteId;
        $this->tareaId = $tareaId;
        $this->estudianteSeleccionado = User::findOrFail($estudianteId);
        $this->tareaSeleccionada = Tarea::findOrFail($tareaId);

        $entrega = Entrega::where('tarea_id', $tareaId)
            ->where('estudiante_id', $estudianteId)
            ->first();

        $this->nota = $entrega ? (string) $entrega->nota : '';
        $this->feedback = $entrega ? (string) $entrega->feedback : '';
        $this->mostrarModalNota = true;
    }

    public function guardarNota(): void
    {
        $this->validate([
            'nota' => ['required', 'numeric', 'min:0', 'max:20'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $entrega = Entrega::firstOrNew([
            'tarea_id' => $this->tareaId,
            'estudiante_id' => $this->estudianteId,
        ]);

        $entrega->nota = $this->nota;
        $entrega->feedback = $this->feedback;
        $entrega->estado = 'calificado';
        if (! $entrega->exists) {
            $entrega->archivo_url = '#';
            $entrega->comentario = 'Nota asignada directamente por el docente en el Libro de Calificaciones.';
        }
        $entrega->save();

        if ($entrega->estudiante) {
            $entrega->estudiante->notify(new TareaCalificada($entrega));
        }

        $this->mostrarModalNota = false;
        $this->reset(['estudianteId', 'tareaId', 'estudianteSeleccionado', 'tareaSeleccionada', 'nota', 'feedback']);
        session()->flash('estado', 'Calificación guardada y promedios recalculados.');
    }

    public function render()
    {
        $estudiantes = $this->curso->inscripciones()
            ->with('estudiante')
            ->get()
            ->pluck('estudiante')
            ->filter();

        $tareas = $this->curso->tareas()->orderBy('id')->get();
        $quizzes = $this->curso->quizzes()->orderBy('id')->get();

        $estudianteIds = $estudiantes->pluck('id');
        $tareaIds = $tareas->pluck('id');
        $quizIds = $quizzes->pluck('id');

        $todasEntregas = Entrega::whereIn('tarea_id', $tareaIds)
            ->whereIn('estudiante_id', $estudianteIds)
            ->get()
            ->groupBy(fn ($e) => "{$e->estudiante_id}_{$e->tarea_id}");

        $todosResultados = ResultadoQuiz::whereIn('quiz_id', $quizIds)
            ->whereIn('estudiante_id', $estudianteIds)
            ->get()
            ->groupBy(fn ($r) => "{$r->estudiante_id}_{$r->quiz_id}");

        // Construir matriz
        $filasMatriz = $estudiantes->map(function (User $estudiante) use ($tareas, $quizzes, $todasEntregas, $todosResultados) {
            $notasTareas = [];
            $sumaNotasPonderadas = 0;
            $sumaPesos = 0;

            foreach ($tareas as $tarea) {
                $entrega = $todasEntregas->get("{$estudiante->id}_{$tarea->id}")?->first();
                $nota = $entrega?->nota;
                $estado = $entrega?->estado ?? 'sin_entrega';

                $notasTareas[$tarea->id] = [
                    'nota' => $nota,
                    'estado' => $estado,
                    'entrega' => $entrega,
                ];

                if ($nota !== null) {
                    $sumaNotasPonderadas += ($nota * $tarea->peso_nota);
                    $sumaPesos += $tarea->peso_nota;
                }
            }

            $notasQuizzes = [];
            $sumaNotasQuizzes = 0;
            $quizzesContados = 0;

            foreach ($quizzes as $quiz) {
                $resultado = $todosResultados->get("{$estudiante->id}_{$quiz->id}")?->first();
                $notaQuiz = $resultado?->puntaje;

                $notasQuizzes[$quiz->id] = [
                    'nota' => $notaQuiz,
                    'resultado' => $resultado,
                ];

                if ($notaQuiz !== null) {
                    $sumaNotasQuizzes += $notaQuiz;
                    $quizzesContados++;
                }
            }

            $promedioTareas = $sumaPesos > 0 ? round($sumaNotasPonderadas / $sumaPesos, 1) : null;
            $promedioQuizzes = $quizzesContados > 0 ? round($sumaNotasQuizzes / $quizzesContados, 1) : null;

            // Promedio final del curso (combinado tareas y quizzes)
            $valores = collect([$promedioTareas, $promedioQuizzes])->filter(fn ($v) => $v !== null);
            $promedioFinal = $valores->isNotEmpty() ? round($valores->avg(), 1) : null;

            return [
                'estudiante' => $estudiante,
                'notasTareas' => $notasTareas,
                'notasQuizzes' => $notasQuizzes,
                'promedioTareas' => $promedioTareas,
                'promedioQuizzes' => $promedioQuizzes,
                'promedioFinal' => $promedioFinal,
            ];
        });

        // Estadísticas generales del curso
        $promediosFinales = $filasMatriz->pluck('promedioFinal')->filter(fn ($v) => $v !== null);
        $promedioCurso = $promediosFinales->isNotEmpty() ? round($promediosFinales->avg(), 1) : null;
        $aprobados = $promediosFinales->filter(fn ($p) => $p >= 10.5)->count();
        $tasaAprobacion = $promediosFinales->isNotEmpty() ? round(($aprobados / $promediosFinales->count()) * 100) : 0;
        $notaMaxima = $promediosFinales->max();
        $notaMinima = $promediosFinales->min();

        return view('livewire.cursos.libro-calificaciones', [
            'tareas' => $tareas,
            'quizzes' => $quizzes,
            'filasMatriz' => $filasMatriz,
            'promedioCurso' => $promedioCurso,
            'aprobados' => $aprobados,
            'tasaAprobacion' => $tasaAprobacion,
            'notaMaxima' => $notaMaxima,
            'notaMinima' => $notaMinima,
        ]);
    }
}
