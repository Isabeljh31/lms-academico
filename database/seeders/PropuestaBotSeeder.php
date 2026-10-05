<?php

namespace Database\Seeders;

use App\Models\EventoCalendario;
use App\Models\PropuestaBot;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Bot\PropuestaBotService;
use App\Services\Calendario\ColoresCursos;
use DateTimeInterface;
use Illuminate\Database\Seeder;

class PropuestaBotSeeder extends Seeder
{
    public function run(): void
    {
        // Genera la(s) propuesta(s) reales a partir de las tareas atrasadas
        // sembradas (mismo camino de código que el scheduler).
        $servicio = app(PropuestaBotService::class);
        $servicio->detectarAtrasos();

        $estudiante = User::where('email', 'estudiante@lms.edu.pe')->first();

        if (! $estudiante) {
            return;
        }

        // Además, un par de propuestas ya resueltas (una aceptada, una
        // rechazada) para poder ver los tres estados en la bandeja de una.
        // Usan tareas vencidas de los cursos del estudiante (datos de
        // DemoAcademicoSeeder; como historial, el estudiante pudo entregarlas
        // después), así la bandeja muestra el color de cada curso y "Ver en
        // calendario" lleva a una actividad real; sin cursos, quedan como
        // ejemplos genéricos. Idempotente: no se rehace una propuesta que ya
        // apunta a una tarea real.
        $tareasConPendiente = PropuestaBot::where('usuario_id', $estudiante->id)->where('estado', 'pendiente')
            ->get()->pluck('datos_propuesta.tarea_id')->filter()->all();

        $tareas = Tarea::whereIn('curso_id', app(ColoresCursos::class)->consultaCursosDe($estudiante)->select('id'))
            ->visiblesParaEstudiante()
            ->where('fecha_entrega', '<', now())
            ->whereNotIn('id', $tareasConPendiente)
            ->with('curso')
            ->orderByDesc('fecha_entrega')
            ->get()
            ->unique('curso_id')
            ->values();

        $aceptada = $this->ejemploResuelto($estudiante, 'aprobado', $tareas->get(0), 'Tarea de ejemplo ya aceptada', 'Historia del Perú', now()->subDays(2)->setTime(18, 0), now()->subDays(3)->setTime(9, 15));

        if (! isset($aceptada->datos_propuesta['evento_calendario_id'])) {
            // Camino normal de una propuesta aprobada: crea el recordatorio en el calendario.
            $evento = $servicio->ejecutarPropuestaAprobada($aceptada);
            $aceptada->update(['datos_propuesta' => [...$aceptada->datos_propuesta, 'evento_calendario_id' => $evento->id]]);
        }

        $this->ejemploResuelto($estudiante, 'rechazado', $tareas->get(1), 'Tarea de ejemplo ya rechazada', 'Introducción a la Programación', now()->subDay()->setTime(18, 0), now()->subDays(2)->setTime(20, 40));
    }

    protected function ejemploResuelto(User $estudiante, string $estado, ?Tarea $tarea, string $tituloEjemplo, string $cursoEjemplo, DateTimeInterface $recordatorio, DateTimeInterface $respuesta): PropuestaBot
    {
        $propuesta = PropuestaBot::firstOrNew(['usuario_id' => $estudiante->id, 'tipo' => 'redistribucion_plan', 'estado' => $estado]);

        if ($propuesta->exists && (($propuesta->datos_propuesta['tarea_id'] ?? 0) !== 0 || ! $tarea)) {
            return $propuesta;
        }

        // Al reemplazar un ejemplo genérico se quita también su recordatorio de ejemplo.
        if (isset($propuesta->datos_propuesta['evento_calendario_id'])) {
            EventoCalendario::where('usuario_id', $estudiante->id)->whereKey($propuesta->datos_propuesta['evento_calendario_id'])->delete();
        }

        $propuesta->fill([
            'datos_propuesta' => [
                'tarea_id' => $tarea?->id ?? 0,
                'tarea_titulo' => $tarea?->titulo ?? $tituloEjemplo,
                'curso_id' => $tarea?->curso_id ?? 0,
                'curso_nombre' => $tarea?->curso->nombre ?? $cursoEjemplo,
                'motivo' => $tarea
                    ? 'La tarea venció el '.$tarea->fecha_entrega->formatoFecha().' sin que se registrara una entrega.'
                    : 'Ejemplo de propuesta ya resuelta por el estudiante.',
                'nueva_fecha_recordatorio' => $recordatorio->format('Y-m-d\TH:i'),
            ],
            'aprobado_por' => $estudiante->id,
            'fecha_respuesta' => $respuesta,
        ])->save();

        return $propuesta;
    }
}
