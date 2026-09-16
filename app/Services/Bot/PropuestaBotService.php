<?php

namespace App\Services\Bot;

use App\Models\Curso;
use App\Models\EventoCalendario;
use App\Models\PropuestaBot;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Funciones acotadas del bot de sugerencias (Módulo 7).
 *
 * Regla de gobernanza no negociable: el bot NUNCA escribe en
 * `eventos_calendario` directamente. Toda sugerencia pasa primero por un
 * registro `propuestas_bot` en estado "pendiente"; el único camino de
 * código que llega a escribir en `eventos_calendario` es
 * `ejecutarPropuestaAprobada()`, y esa función exige recibir una
 * `PropuestaBot` ya en estado "aprobado" con `aprobado_por` registrado.
 * No existe ningún otro método en esta clase (ni en el resto de la app)
 * que cree o modifique un `EventoCalendario` a partir de una propuesta.
 */
class PropuestaBotService
{
    /**
     * [Solo lectura] Progreso de un estudiante en un curso: entregas,
     * tareas vencidas sin entregar, promedio de notas.
     */
    public function leerProgresoEstudiante(User $estudiante, Curso $curso): array
    {
        $tareas = $curso->tareas()->with(['entregas' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])->get();

        $vencidasSinEntrega = $tareas->filter(fn ($t) => $t->estaVencida() && $t->entregas->isEmpty());
        $calificadas = $tareas->flatMap->entregas->where('estado', 'calificado');

        return [
            'total_tareas' => $tareas->count(),
            'tareas_vencidas_sin_entrega' => $vencidasSinEntrega->values(),
            'promedio_notas' => $calificadas->isNotEmpty() ? round((float) $calificadas->avg('nota'), 2) : null,
        ];
    }

    /**
     * [Solo lectura] Eventos de calendario de un usuario.
     */
    public function leerCalendario(User $usuario): Collection
    {
        return $usuario->eventosCalendario()->orderBy('fecha')->get();
    }

    /**
     * [Solo lectura / propuesta] Detecta atraso y genera —si corresponde—
     * una fila `propuestas_bot` en estado "pendiente". Nunca toca
     * `eventos_calendario`. Evita duplicar una propuesta pendiente para
     * la misma tarea.
     */
    public function sugerirRedistribucionPlan(User $estudiante, Curso $curso): ?PropuestaBot
    {
        $progreso = $this->leerProgresoEstudiante($estudiante, $curso);

        $tareaAtrasada = $progreso['tareas_vencidas_sin_entrega']->first();

        if (! $tareaAtrasada) {
            return null;
        }

        $yaExiste = PropuestaBot::where('usuario_id', $estudiante->id)
            ->where('estado', 'pendiente')
            ->where('tipo', 'redistribucion_plan')
            ->get()
            ->contains(fn ($p) => ($p->datos_propuesta['tarea_id'] ?? null) === $tareaAtrasada->id);

        if ($yaExiste) {
            return null;
        }

        return PropuestaBot::create([
            'usuario_id' => $estudiante->id,
            'tipo' => 'redistribucion_plan',
            'estado' => 'pendiente',
            'datos_propuesta' => [
                'tarea_id' => $tareaAtrasada->id,
                'tarea_titulo' => $tareaAtrasada->titulo,
                'curso_id' => $curso->id,
                'curso_nombre' => $curso->nombre,
                'motivo' => 'La tarea venció el '.$tareaAtrasada->fecha_entrega->format('d/m/Y').' sin que se registrara una entrega.',
                'nueva_fecha_recordatorio' => now()->addDay()->setTime(18, 0)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Recorre estudiantes inscritos con tareas vencidas y genera
     * propuestas pendientes. Pensado para el Scheduler (ver
     * routes/console.php). Solo lectura + creación de propuestas.
     */
    public function detectarAtrasos(): int
    {
        $creadas = 0;

        Curso::with('inscripciones.estudiante')->get()->each(function (Curso $curso) use (&$creadas) {
            $curso->inscripciones->each(function ($inscripcion) use ($curso, &$creadas) {
                if ($this->sugerirRedistribucionPlan($inscripcion->estudiante, $curso)) {
                    $creadas++;
                }
            });
        });

        return $creadas;
    }

    /**
     * Único punto de código que escribe en `eventos_calendario` a partir
     * de una propuesta del bot. Exige una propuesta ya aprobada.
     */
    public function ejecutarPropuestaAprobada(PropuestaBot $propuesta): EventoCalendario
    {
        abort_unless($propuesta->estado === 'aprobado' && $propuesta->aprobado_por, 409, 'La propuesta no está aprobada.');

        return EventoCalendario::create([
            'usuario_id' => $propuesta->usuario_id,
            'titulo' => 'Recordatorio: '.$propuesta->datos_propuesta['tarea_titulo'],
            'fecha' => $propuesta->datos_propuesta['nueva_fecha_recordatorio'],
            'tipo' => 'recordatorio',
        ]);
    }
}
