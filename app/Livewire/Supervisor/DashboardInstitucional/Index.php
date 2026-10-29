<?php

namespace App\Livewire\Supervisor\DashboardInstitucional;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\ConversacionBot;
use App\Models\Curso;
use App\Models\EsquemaEvaluacion;
use App\Models\Inscripcion;
use App\Models\NotaRubro;
use App\Models\PropuestaBot;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Evaluacion\CalculadoraNotas;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard institucional y reportes administrativos (Supervisor).
 * ERS RF-90: indicadores académicos globales.
 * ERS RF-91: nuevos estudiantes, activos/inactivos, cursos completados,
 *            tasa de abandono, promedio y aprobación por curso.
 *
 * Definiciones:
 * - Tasa de abandono: matrículas dadas de baja (o sin acceso local) sobre el
 *   total de matrículas.
 * - Cursos completados: cursos archivados (finalizados).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    /** Período en días para "nuevos estudiantes". */
    public int $periodo = 30;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
    }

    public function updatedPeriodo(): void
    {
        $this->periodo = in_array($this->periodo, [7, 30, 90, 365], true) ? $this->periodo : 30;
    }

    /**
     * Cursos con más entregas atrasadas: tareas visibles vencidas sin entrega
     * de estudiantes con matrícula activa.
     */
    protected function cursosConMasAtraso(int $limite = 5): Collection
    {
        $tareasVencidas = Tarea::visiblesParaEstudiante()
            ->where('fecha_entrega', '<', now())
            ->with('entregas:id,tarea_id,estudiante_id')
            ->get()
            ->groupBy('curso_id');

        // Solo cursos en marcha: los archivados son historial del periodo anterior.
        return Curso::whereIn('id', $tareasVencidas->keys())
            ->where('estado', 'publicado')
            ->with(['inscripcionesActivas:id,curso_id,estudiante_id'])
            ->get()
            ->map(function (Curso $curso) use ($tareasVencidas) {
                $estudiantes = $curso->inscripcionesActivas->pluck('estudiante_id');
                $atrasos = $tareasVencidas[$curso->id]->sum(
                    fn (Tarea $t) => $estudiantes->diff($t->entregas->pluck('estudiante_id'))->count()
                );

                return ['curso' => $curso, 'atrasos' => $atrasos];
            })
            ->filter(fn ($fila) => $fila['atrasos'] > 0)
            ->sortByDesc('atrasos')
            ->take($limite)
            ->values();
    }

    /**
     * ERS RF-91: promedio de nota final y aprobación por curso.
     */
    protected function rendimientoPorCurso(CalculadoraNotas $calculadora): Collection
    {
        return Curso::with('esquemaEvaluacion.rubros', 'profesor')
            ->withCount(['inscripcionesActivas', 'inscripciones'])
            ->orderBy('nombre')
            ->get()
            ->map(function (Curso $curso) use ($calculadora) {
                $notas = collect($calculadora->resumenDelCurso($curso, $curso->inscripcionesActivas()->pluck('estudiante_id')));
                $rendimientos = $notas->pluck('rendimiento')->filter(fn ($n) => $n !== null);
                $bajas = $curso->inscripciones_count - $curso->inscripciones_activas_count;

                return [
                    'curso' => $curso,
                    'activos' => $curso->inscripciones_activas_count,
                    'promedio' => $rendimientos->isNotEmpty() ? round($rendimientos->avg(), 1) : null,
                    'aprobacion' => $calculadora->tasaAprobacion($notas),
                    'abandono' => $curso->inscripciones_count > 0 ? (int) round($bajas / $curso->inscripciones_count * 100) : null,
                ];
            });
    }

    public function render(CalculadoraNotas $calculadora)
    {
        $desde = now()->subDays($this->periodo);
        $estudiantes = User::role(Rol::Estudiante->value);
        $totalInscripciones = Inscripcion::count();
        $bajas = Inscripcion::where(fn ($q) => $q->where('estado', 'inactivo')->orWhere('activo_local', false))->count();
        $notaMinima = $calculadora->notaMinima();
        $totalNotas = NotaRubro::whereNotNull('nota')->count();

        return view('livewire.supervisor.dashboard-institucional.index', [
            'notaMinima' => $notaMinima,
            'usuariosPorRol' => collect(Rol::cases())->mapWithKeys(fn (Rol $rol) => [$rol->etiqueta() => User::role($rol->value)->count()]),
            'nuevosEstudiantes' => (clone $estudiantes)->where('created_at', '>=', $desde)->count(),
            'estudiantesActivos' => (clone $estudiantes)->where('activo', true)->count(),
            'estudiantesInactivos' => (clone $estudiantes)->where('activo', false)->count(),
            'usuariosPrimerLogin' => User::where('primer_login', true)->where('activo', true)->count(),
            'cursosPorEstado' => Curso::selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado'),
            'totalInscripciones' => $totalInscripciones,
            'tasaAbandono' => $totalInscripciones > 0 ? round($bajas / $totalInscripciones * 100, 1) : 0,
            'rendimiento' => $this->rendimientoPorCurso($calculadora),
            'cursosConMasAtraso' => $this->cursosConMasAtraso(),
            'cursosSinEsquema' => Curso::count() - EsquemaEvaluacion::has('rubros')->count(),
            'distribucion' => [
                '18 a 20' => NotaRubro::whereBetween('nota', [18, 20])->count(),
                '14 a 17.9' => NotaRubro::whereBetween('nota', [14, 17.9])->count(),
                $notaMinima.' a 13.9' => NotaRubro::whereBetween('nota', [$notaMinima, 13.9])->count(),
                'Menos de '.$notaMinima => NotaRubro::where('nota', '<', $notaMinima)->count(),
            ],
            'totalNotas' => $totalNotas,
            'usoBot' => [
                'Conversaciones' => ConversacionBot::count(),
                'Propuestas pendientes' => PropuestaBot::where('estado', 'pendiente')->count(),
                'Aceptadas' => PropuestaBot::where('estado', 'aprobado')->count(),
                'Rechazadas' => PropuestaBot::where('estado', 'rechazado')->count(),
            ],
            'auditoriasRecientes' => BitacoraAuditoria::with(['ejecutadoPor', 'curso'])->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(),
        ])->title('Dashboard institucional — LearnUp');
    }
}
