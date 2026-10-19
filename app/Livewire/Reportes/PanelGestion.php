<?php

namespace App\Livewire\Reportes;

use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Evaluacion\CalculadoraNotas;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Panel de inicio de Profesor y Supervisor.
 * ERS RF-88 (Profesor): cursos a cargo, estudiantes, tareas pendientes de
 * calificar, quizzes activos y promedio general.
 * ERS RF-90 (Supervisor): estudiantes activos, profesores activos, cursos
 * activos, tasa de finalización y entregas pendientes globales.
 */
class PanelGestion extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['profesor', 'supervisor']), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function datosProfesor(CalculadoraNotas $calculadora): array
    {
        // Los cursos archivados (periodos anteriores) se listan al final y no suman en los indicadores.
        $todos = Curso::dictadosPor(auth()->id())
            ->withCount('inscripcionesActivas')
            ->with('esquemaEvaluacion.rubros')
            ->orderByRaw("estado = 'archivado'")
            ->orderBy('nombre')
            ->get();
        $cursos = $todos->reject(fn (Curso $curso) => $curso->estaFinalizado())->values();

        $pendientesPorCurso = Entrega::where('estado', 'entregado')
            ->whereHas('tarea', fn ($q) => $q->whereIn('curso_id', $cursos->pluck('id')))
            ->with('tarea:id,curso_id')
            ->get()
            ->countBy(fn (Entrega $e) => $e->tarea->curso_id);

        $promediosPorCurso = $cursos->mapWithKeys(function (Curso $curso) use ($calculadora) {
            $ids = $curso->inscripcionesActivas()->pluck('estudiante_id');
            // Rendimiento a la fecha: promedio ponderado de los rubros ya calificados.
            $notas = collect($calculadora->resumenDelCurso($curso, $ids))->pluck('rendimiento')->filter(fn ($n) => $n !== null);

            return [$curso->id => $notas->isNotEmpty() ? round($notas->avg(), 1) : null];
        });

        $conPromedio = $promediosPorCurso->filter(fn ($p) => $p !== null);

        return [
            'cursos' => $todos,
            'cursosActivos' => $cursos->count(),
            'pendientesPorCurso' => $pendientesPorCurso,
            'promediosPorCurso' => $promediosPorCurso,
            'totalEstudiantes' => $cursos->sum('inscripciones_activas_count'),
            'totalPendientes' => $pendientesPorCurso->sum(),
            'quizzesActivos' => Quiz::whereIn('curso_id', $cursos->pluck('id'))
                ->visiblesParaEstudiante()
                ->get()
                ->filter(fn (Quiz $q) => $q->estaAbierto())
                ->count(),
            'promedioGeneral' => $conPromedio->isNotEmpty() ? round($conPromedio->avg(), 1) : null,
        ];
    }

    /**
     * Tasa de finalización: porcentaje de estudiantes con nota final
     * aprobatoria entre los que tienen nota en cursos archivados (finalizados).
     *
     * @return array<string, mixed>
     */
    protected function datosSupervisor(CalculadoraNotas $calculadora): array
    {
        $finalizados = Curso::where('estado', 'archivado')->with('esquemaEvaluacion.rubros')->get();
        $notas = $finalizados->flatMap(fn (Curso $curso) => collect(
            $calculadora->resumenDelCurso($curso, $curso->inscripciones()->where('estado', 'activo')->pluck('estudiante_id'))
        )->values());

        return [
            'estudiantesActivos' => User::role('estudiante')->where('activo', true)->count(),
            'profesoresActivos' => User::role('profesor')->where('activo', true)->count(),
            'cursosActivos' => Curso::where('estado', 'publicado')->count(),
            'tasaFinalizacion' => $calculadora->tasaAprobacion($notas),
            'entregasPendientes' => Entrega::where('estado', 'entregado')->count(),
            'cursosSinEsquema' => Curso::where('estado', '!=', 'archivado')->whereDoesntHave('esquemaEvaluacion.rubros')->count(),
            'cuentasPrimerIngreso' => User::where('primer_login', true)->where('activo', true)->count(),
        ];
    }

    protected function comunicados(): Collection
    {
        return Comunicado::activos()
            ->whereNull('curso_id')
            ->paraRol(auth()->user()->rolPrincipal() ?? 'profesor')
            ->latest()
            ->take(3)
            ->get();
    }

    public function render(CalculadoraNotas $calculadora)
    {
        $esSupervisor = auth()->user()->hasRole('supervisor');

        return view('livewire.reportes.panel-gestion', [
            'esSupervisor' => $esSupervisor,
            'comunicados' => $this->comunicados(),
            'notaMinima' => $calculadora->notaMinima(),
            ...($esSupervisor ? $this->datosSupervisor($calculadora) : $this->datosProfesor($calculadora)),
        ]);
    }
}
