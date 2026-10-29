<?php

namespace App\Livewire\Estudiante;

use App\Models\EventoCalendario;
use App\Models\Tarea;
use App\Services\Reportes\ProgresoAcademicoService;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Panel de inicio del rol estudiante (rediseño visual, no toca lógica de
 * otros módulos): hero con progreso general, widgets de cursos, próximas
 * entregas, mini-calendario del mes y accesos rápidos. Solo se embebe
 * desde resources/views/dashboard.blade.php cuando el usuario es
 * estudiante.
 */
class PanelInicio extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('estudiante'), 403);
    }

    /**
     * Todos los eventos propios + fechas de entrega de tareas de los
     * cursos inscritos, dentro del mes en curso (sin filtrar por fecha
     * futura: el mini-calendario muestra el mes completo). Igual criterio
     * de datos que App\Livewire\Compartido\Calendario\Index, pero de solo
     * lectura y sin su lógica de creación/borrado — no se duplica esa.
     */
    protected function eventosDelMes(): Collection
    {
        $usuario = auth()->user();
        $inicioMes = now()->startOfMonth();
        $finMes = now()->endOfMonth();

        $propios = $usuario->eventosCalendario()
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->get()
            ->map(fn (EventoCalendario $e) => [
                'titulo' => $e->titulo,
                'fecha' => $e->fecha,
                'tipo' => $e->tipo,
            ]);

        $cursoIds = $usuario->inscripciones()->pluck('curso_id');

        $tareas = Tarea::whereIn('curso_id', $cursoIds)
            ->where('visible', true)
            ->whereBetween('fecha_entrega', [$inicioMes, $finMes])
            ->with('curso')
            ->get()
            ->map(fn (Tarea $t) => [
                'titulo' => $t->titulo.' — '.$t->curso->nombre,
                'fecha' => $t->fecha_entrega,
                'tipo' => 'tarea',
            ]);

        return $propios->concat($tareas)->sortBy('fecha')->values();
    }

    /**
     * Estructura lista para pintar una grilla de mes con Alpine (sin
     * FullCalendar): día de hoy, offset del primer día y los eventos
     * agrupados por día del mes.
     */
    protected function calendarioMes(Collection $eventosDelMes): array
    {
        $hoy = now();
        $inicioMes = $hoy->copy()->startOfMonth();

        return [
            'nombre_mes' => $inicioMes->translatedFormat('F Y'),
            'hoy' => $hoy->day,
            'total_dias' => $inicioMes->daysInMonth,
            // Lunes = 0 ... Domingo = 6, para alinear la grilla de 7 columnas.
            'offset_primer_dia' => ($inicioMes->dayOfWeekIso - 1),
            'dias_con_evento' => $eventosDelMes
                ->groupBy(fn ($e) => $e['fecha']->day)
                ->map(fn ($grupo) => $grupo->pluck('titulo')->all())
                ->all(),
        ];
    }

    public function render(ProgresoAcademicoService $servicio)
    {
        $resumen = $servicio->resumenDashboardEstudiante(auth()->user());
        $eventosDelMes = $this->eventosDelMes();

        return view('livewire.estudiante.panel-inicio', [
            ...$resumen,
            'proximosEventos' => $eventosDelMes->filter(fn ($e) => $e['fecha']->isFuture())->take(5),
            'calendario' => $this->calendarioMes($eventosDelMes),
        ]);
    }
}
