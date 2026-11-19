<?php

namespace App\Services\Asistencia;

use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\SesionClase;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * ERS RF-71: porcentaje de asistencia acumulado de un estudiante.
 *
 * Criterio:
 * - Solo cuentan las sesiones ya realizadas (fecha pasada).
 * - Presente y Tardanza cuentan como asistencia.
 * - Justificado no cuenta a favor ni en contra: se excluye del total.
 * - Una sesión realizada sin registro para el estudiante cuenta como Ausente.
 */
class CalculadoraAsistencia
{
    public const ASISTIO = ['presente', 'tardanza'];

    /**
     * @param  iterable<string|null>  $estadosPorSesion  estado de cada sesión realizada (null = sin registro)
     */
    public function porcentaje(iterable $estadosPorSesion): ?float
    {
        $consideradas = 0;
        $asistidas = 0;

        foreach ($estadosPorSesion as $estado) {
            if ($estado === 'justificado') {
                continue;
            }

            $consideradas++;

            if (in_array($estado, self::ASISTIO, true)) {
                $asistidas++;
            }
        }

        return $consideradas > 0 ? round($asistidas / $consideradas * 100, 1) : null;
    }

    /**
     * Historial del estudiante en el curso: cada sesión realizada con su
     * estado (ausente si no hay registro).
     *
     * @return Collection<int, array{sesion: SesionClase, estado: string, observacion: string|null}>
     */
    public function historial(User $estudiante, Curso $curso): Collection
    {
        $registros = Asistencia::where('estudiante_id', $estudiante->id)
            ->whereIn('sesion_id', $curso->sesionesClase()->select('id'))
            ->get()
            ->keyBy('sesion_id');

        return $curso->sesionesClase()
            ->where('fecha', '<=', now())
            ->get()
            ->map(fn (SesionClase $sesion) => [
                'sesion' => $sesion,
                'estado' => $registros->get($sesion->id)?->estado ?? 'ausente',
                'observacion' => $registros->get($sesion->id)?->observacion,
            ]);
    }

    public function porcentajeDe(User $estudiante, Curso $curso): ?float
    {
        return $this->porcentaje($this->historial($estudiante, $curso)->pluck('estado'));
    }

    /**
     * Porcentaje de asistencia de varios estudiantes en el curso, con
     * consultas por lote (2 en total, no 2 por estudiante): para listados
     * donde se necesita el dato de muchos estudiantes a la vez.
     *
     * @param  Collection<int, int>  $estudianteIds
     * @return array<int, float|null> estudiante_id => porcentaje
     */
    public function porcentajesDelCurso(Curso $curso, Collection $estudianteIds): array
    {
        $sesionesRealizadas = $curso->sesionesClase()->where('fecha', '<=', now())->pluck('id');

        $registrosPorEstudiante = Asistencia::whereIn('estudiante_id', $estudianteIds)
            ->whereIn('sesion_id', $sesionesRealizadas)
            ->get()
            ->groupBy('estudiante_id');

        return $estudianteIds->mapWithKeys(function (int $id) use ($sesionesRealizadas, $registrosPorEstudiante) {
            $registros = ($registrosPorEstudiante[$id] ?? collect())->keyBy('sesion_id');
            $estados = $sesionesRealizadas->map(fn ($sesionId) => $registros->get($sesionId)?->estado ?? 'ausente');

            return [$id => $this->porcentaje($estados)];
        })->all();
    }
}
