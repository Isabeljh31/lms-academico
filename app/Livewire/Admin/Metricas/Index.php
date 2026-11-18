<?php

namespace App\Livewire\Admin\Metricas;

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

        // Asistencia y rendimiento en lote por curso (unas pocas consultas por curso, no varias por estudiante y curso).
        $porcentajes = [];
        $rendimientos = [];
        $cursos = $estudiantes->flatMap(fn (User $estudiante) => $estudiante->inscripciones->pluck('curso'))
            ->filter(fn ($curso) => $curso && ! $curso->estaFinalizado())
            ->unique('id');
        foreach ($cursos as $curso) {
            $ids = $estudiantes->filter(fn (User $estudiante) => $estudiante->inscripciones->contains('curso_id', $curso->id))->pluck('id')->values();
            $rendimientos[$curso->id] = collect($notas->resumenDelCurso($curso, $ids))->map(fn (array $resumen) => $resumen['rendimiento'])->all();
            if ($curso->usa_asistencia) {
                $porcentajes[$curso->id] = $asistencia->porcentajesDelCurso($curso, $ids);
            }
        }

        $enRiesgo = $estudiantes->map(function (User $estudiante) use ($porcentajes, $rendimientos, $notaMinima) {
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
                    $porcentaje = $porcentajes[$curso->id][$estudiante->id] ?? null;

                    if ($porcentaje !== null && $porcentaje < self::ASISTENCIA_MINIMA) {
                        $critico = true;
                        $motivos[] = "Asistencia de {$porcentaje} % en {$curso->nombre}";
                    }
                }

                $rendimiento = $rendimientos[$curso->id][$estudiante->id] ?? null;

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

        return view('livewire.admin.metricas.index', [
            'estudiantesEnRiesgo' => $enRiesgo,
            'notaMinima' => $notaMinima,
            'totalEstudiantes' => $totalEstudiantes,
            'promedioInscripciones' => $totalEstudiantes > 0 ? round($inscripcionesActivas / $totalEstudiantes, 1) : 0,
            'totalMensajes' => Mensaje::count(),
            'totalInteraccionesIA' => ConversacionBot::count(),
        ])->title('Riesgo de abandono — LearnUp');
    }
}
