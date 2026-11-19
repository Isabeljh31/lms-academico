<?php

namespace App\Livewire\Supervisor\Estudiantes;

use App\Enums\Rol;
use App\Livewire\Supervisor\Estudiantes\Concerns\GestionaEstudiante;
use App\Models\Curso;
use App\Models\User;
use App\Services\Asistencia\CalculadoraAsistencia;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Reportes\ProgresoAcademicoService;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ficha de detalle de un estudiante (Supervisor): datos personales, apoderado,
 * avance por curso y riesgo de abandono. El directorio (Index) se queda
 * compacto; esta pantalla es donde se ve todo.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use GestionaEstudiante;

    public User $estudiante;

    public function mount(User $estudiante): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        abort_unless($estudiante->hasRole(Rol::Estudiante->value), 404);

        $this->estudiante = $estudiante;
    }

    /**
     * Sobrescribe la del trait: acá hay que salir de la ficha, no solo
     * refrescar la lista, porque el estudiante que se borra es el que se ve.
     */
    public function eliminarCuenta(int $id, GestorCuentas $gestor): void
    {
        $nombre = $this->estudiante->name;
        $gestor->eliminar(User::role(Rol::Estudiante->value)->findOrFail($id));
        session()->flash('estado', "Estudiante \"{$nombre}\" eliminado correctamente.");
        $this->redirect(route('supervisor.estudiantes.index'), navigate: true);
    }

    public function render(ProgresoAcademicoService $progreso, CalculadoraAsistencia $asistencia, CalculadoraNotas $notas)
    {
        $this->estudiante->refresh()->load(['inscripciones.curso', 'inscripciones.dadoDeBajaPor']);

        $inscripciones = $this->estudiante->inscripciones;

        $inscripcionesActivas = $inscripciones->filter(fn ($i) => $i->curso && $i->estaActiva() && ! $i->curso->estaFinalizado());
        $inscripcionesHistorial = $inscripciones->filter(fn ($i) => $i->curso && ! ($i->estaActiva() && ! $i->curso->estaFinalizado()));

        // Un solo recorrido por curso: antes se volvían a consultar tareas/quizzes/
        // asistencia/rendimiento por separado dentro de calcularRiesgo() y de
        // resumenDashboardEstudiante() (que además volvía a traer los cursos desde
        // cero), multiplicando las consultas por cada curso del estudiante.
        $pendientes = collect();
        $asistenciaPorCurso = [];
        $rendimientoPorCurso = [];

        $cursosConMetricas = $inscripcionesActivas->map(function ($inscripcion) use ($progreso, $asistencia, &$pendientes, &$asistenciaPorCurso, &$rendimientoPorCurso) {
            /** @var Curso $curso */
            $curso = $inscripcion->curso;
            $estudiante = $this->estudiante;
            $resumenRubros = $progreso->resumenRubros($estudiante, $curso);
            $asistenciaPct = $curso->usa_asistencia ? $asistencia->porcentajeDe($estudiante, $curso) : null;

            $asistenciaPorCurso[$curso->id] = [$estudiante->id => $asistenciaPct];
            $rendimientoPorCurso[$curso->id] = [$estudiante->id => $resumenRubros['rendimiento']];

            $tareas = $curso->tareas()->visiblesParaEstudiante()
                ->with(['entregas' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
                ->get()
                ->filter(fn ($tarea) => Gate::forUser($estudiante)->allows('ver', $tarea));

            $quizzes = $curso->quizzes()->visiblesParaEstudiante()
                ->with(['resultados' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
                ->get();

            foreach ($tareas as $tarea) {
                if ($tarea->entregas->isEmpty()) {
                    $pendientes->push([
                        'tipo' => 'tarea',
                        'titulo' => $tarea->titulo,
                        'curso' => $curso,
                        'fecha' => $tarea->fecha_entrega,
                        'atrasado' => $tarea->estaVencida(),
                        'url' => route('cursos.tareas.show', [$curso, $tarea]),
                    ]);
                }
            }

            foreach ($quizzes as $quiz) {
                if ($quiz->resultados->isEmpty() && $quiz->estaAbierto()) {
                    $pendientes->push([
                        'tipo' => 'quiz',
                        'titulo' => $quiz->tema,
                        'curso' => $curso,
                        'fecha' => $quiz->fecha_limite,
                        'atrasado' => false,
                        'url' => route('cursos.quizzes.show', [$curso, $quiz]),
                    ]);
                }
            }

            return [
                'curso' => $curso,
                'progreso' => $progreso->avance($estudiante, $curso),
                'nota_final' => $resumenRubros['nota_final'],
                'condicion' => $resumenRubros['condicion'],
                'tiene_esquema' => $resumenRubros['esquema'] !== null,
                'asistencia' => $asistenciaPct,
            ];
        })->values();

        $riesgo = $this->calcularRiesgoDesdeLote($this->estudiante, $inscripcionesActivas->pluck('curso'), $asistenciaPorCurso, $rendimientoPorCurso, $notas->notaMinima());

        $historial = $inscripcionesHistorial->map(fn ($inscripcion) => [
            'curso' => $inscripcion->curso,
            'motivo' => $inscripcion->curso->estaFinalizado() && $inscripcion->estaActiva() ? 'finalizado' : 'baja',
            'fecha' => $inscripcion->estaActiva() ? $inscripcion->curso->fecha_fin : $inscripcion->fecha_baja,
            'dado_de_baja_por' => $inscripcion->dadoDeBajaPor,
        ])->sortByDesc(fn (array $h) => $h['fecha'])->values();

        return view('livewire.supervisor.estudiantes.show', [
            'cursosConMetricas' => $cursosConMetricas,
            'riesgoNivel' => $riesgo['nivel'],
            'riesgoMotivos' => $riesgo['motivos'],
            'historial' => $historial,
            'proximasEntregas' => $pendientes->sortBy(fn ($p) => $p['fecha'] ?? now()->addYears(10))->take(5)->values(),
            'totalPendientes' => $pendientes->count(),
            'usuarioEditar' => $this->estudiante,
        ])->title("{$this->estudiante->name} - LearnUp");
    }
}
