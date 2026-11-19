<?php

namespace App\Livewire\Estudiante;

use App\Models\EventoCalendario;
use App\Models\Tarea;
use App\Services\Reportes\ProgresoAcademicoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Panel de inicio del estudiante: cursos, progreso, próximas entregas,
 * minicalendario y accesos rápidos.
 */
class PanelInicio extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('estudiante'), 403);
    }

    /**
     * Obtiene los eventos personales y las fechas de tareas autorizadas
     * para el estudiante durante el mes actual.
     */
    protected function eventosDelMes(): Collection
    {
        $usuario = auth()->user();
        $inicioMes = now()->startOfMonth();
        $finMes = now()->endOfMonth();

        $propios = $usuario->eventosCalendario()
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->get()
            ->map(fn (EventoCalendario $evento) => [
                'titulo' => $evento->titulo,
                'fecha' => $evento->fecha,
                'tipo' => $evento->tipo,
            ]);

        $cursoIds = $usuario->inscripciones()->pluck('curso_id');

        $tareas = Tarea::whereIn('curso_id', $cursoIds)
            ->where('visible', true)
            ->whereBetween('fecha_entrega', [$inicioMes, $finMes])
            ->with('curso')
            ->get()
            ->filter(
                fn (Tarea $tarea) => Gate::forUser($usuario)->allows('ver', $tarea)
            )
            ->map(fn (Tarea $tarea) => [
                'titulo' => $tarea->titulo.' — '.$tarea->curso->nombre,
                'fecha' => $tarea->fecha_entrega,
                'tipo' => 'tarea',
            ]);

        return $propios->concat($tareas)->sortBy('fecha')->values();
    }

    /**
     * Prepara los datos del minicalendario del mes actual.
     */
    protected function calendarioMes(Collection $eventosDelMes): array
    {
        $hoy = now();
        $inicioMes = $hoy->copy()->startOfMonth();

        return [
            'nombre_mes' => $inicioMes->translatedFormat('F Y'),
            'hoy' => $hoy->day,
            'total_dias' => $inicioMes->daysInMonth,
            // Lunes = 0 ... Domingo = 6.
            'offset_primer_dia' => $inicioMes->dayOfWeekIso - 1,
            'dias_con_evento' => $eventosDelMes
                ->groupBy(fn ($evento) => $evento['fecha']->day)
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
            'proximosEventos' => $eventosDelMes
                ->filter(fn ($evento) => $evento['fecha']->isFuture())
                ->take(5),
            'calendario' => $this->calendarioMes($eventosDelMes),
        ]);
    }
}