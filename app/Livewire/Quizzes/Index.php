<?php

namespace App\Livewire\Quizzes;

use App\Models\Curso;
use App\Notifications\NuevoQuizPublicado;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
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

    /** Vista de docente (Profesor del curso o Supervisor en solo lectura). */
    public function getEsVistaDocenteProperty(): bool
    {
        return Gate::allows('supervisar', $this->curso);
    }

    /**
     * RF-37: publica u oculta el quiz; al publicarse por primera vez se
     * notifica a los matriculados (RF-84).
     */
    public function alternarVisibilidad(int $quizId): void
    {
        $quiz = $this->curso->quizzes()->findOrFail($quizId);
        Gate::authorize('gestionar', $quiz);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado.');

        $eraVisible = $quiz->esVisibleParaEstudiante();

        $quiz->update($quiz->visible
            ? ['visible' => false]
            : ['visible' => true, 'estado_contenido' => 'publicado', 'publicar_en' => null]);

        if (! $eraVisible && $quiz->fresh()->esVisibleParaEstudiante()) {
            $estudiantes = $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter();
            Notification::send($estudiantes, new NuevoQuizPublicado($quiz));
        }
    }

    public function eliminar(int $quizId): void
    {
        $quiz = $this->curso->quizzes()->findOrFail($quizId);
        Gate::authorize('eliminar', $quiz);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado.');

        $quiz->delete();
        session()->flash('estado', "Quiz \"{$quiz->tema}\" eliminado.");
    }

    public function render()
    {
        $usuario = auth()->user();
        $esGestor = $this->esVistaDocente;

        $quizzes = $this->curso->quizzes()
            ->with(['modulo', 'resultados' => fn ($q) => $esGestor ? $q : $q->where('estudiante_id', $usuario->id)])
            ->when(! $esGestor, fn ($q) => $q->visiblesParaEstudiante())
            ->orderBy('created_at')
            ->get();

        return view('livewire.quizzes.index', ['quizzes' => $quizzes])
            ->title("Quizzes — {$this->curso->nombre}");
    }
}
