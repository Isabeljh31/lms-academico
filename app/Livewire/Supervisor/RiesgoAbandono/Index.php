<?php

namespace App\Livewire\Supervisor\RiesgoAbandono;

use App\Models\ConversacionBot;
use App\Models\Inscripcion;
use App\Models\Mensaje;
use App\Models\User;
use App\Services\Asistencia\CalculadoraAsistencia;
use App\Services\Evaluacion\CalculadoraNotas;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Analítica de riesgo de abandono y engagement (ERS 11.3, Fase 3).
 *
 * Un estudiante está en riesgo en un curso si su asistencia acumulada es
 * menor a 50 % (en cursos que registran asistencia) o si su nota final
 * ponderada está bajo la nota mínima aprobatoria. Nivel Alto: riesgo en
 * más de un curso.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public const ASISTENCIA_MINIMA = 50;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
    }

    public function render(CalculadoraAsistencia $asistencia, CalculadoraNotas $notas)
    {
        $notaMinima = $notas->notaMinima();

        $estudiantes = User::role('estudiante')
            ->where('activo', true)
            ->with(['inscripciones' => fn ($q) => $q->where('estado', 'activo')->where('activo_local', true)->with('curso.esquemaEvaluacion.rubros')])
            ->get();

        $enRiesgo = $estudiantes->map(function (User $estudiante) use ($asistencia, $notas, $notaMinima) {
            $motivos = [];
            $cursosCriticos = 0;

            foreach ($estudiante->inscripciones as $inscripcion) {
                $curso = $inscripcion->curso;
                $critico = false;

                // Un curso archivado es historial del periodo anterior: no es riesgo actual.
                if (! $curso || $curso->estaFinalizado()) {
                    continue;
                }

                if ($curso->usa_asistencia) {
                    $porcentaje = $asistencia->porcentajeDe($estudiante, $curso);

                    if ($porcentaje !== null && $porcentaje < self::ASISTENCIA_MINIMA) {
                        $critico = true;
                        $motivos[] = "Asistencia de {$porcentaje} % en {$curso->nombre}";
                    }
                }

                $rendimiento = $notas->rendimientoDe($estudiante, $curso);

                if ($rendimiento !== null && $rendimiento < $notaMinima) {
                    $critico = true;
                    $motivos[] = "Rendimiento de {$rendimiento} en {$curso->nombre}";
                }

                $cursosCriticos += $critico ? 1 : 0;
            }

            return $cursosCriticos > 0
                ? ['estudiante' => $estudiante, 'nivel' => $cursosCriticos > 1 ? 'Alto' : 'Medio', 'motivos' => $motivos]
                : null;
        })->filter()->sortBy(fn ($r) => $r['nivel'] === 'Alto' ? 0 : 1)->values();

        $totalEstudiantes = $estudiantes->count();
        $inscripcionesActivas = Inscripcion::where('estado', 'activo')->where('activo_local', true)->count();

        return view('livewire.supervisor.riesgo-abandono.index', [
            'estudiantesEnRiesgo' => $enRiesgo,
            'notaMinima' => $notaMinima,
            'totalEstudiantes' => $totalEstudiantes,
            'promedioInscripciones' => $totalEstudiantes > 0 ? round($inscripcionesActivas / $totalEstudiantes, 1) : 0,
            'totalMensajes' => Mensaje::count(),
            'totalInteraccionesIA' => ConversacionBot::count(),
        ])->title('Riesgo de abandono — LearnUp');
    }
}
