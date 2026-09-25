<?php

namespace App\Livewire\PanelAdmin;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\ConversacionBot;
use App\Models\Curso;
use App\Models\EsquemaEvaluacion;
use App\Models\Inscripcion;
use App\Models\NotaRubro;
use App\Models\PropuestaBot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Métricas globales — Supervisor.
 * Fase 2: integra datos del Esquema de Evaluación (RF-54 a RF-65).
 */
#[Layout('layouts.app')]
class Metricas extends Component
{
    protected function cursosConMasAtraso(int $limite = 5)
    {
        return Curso::with(['tareas.entregas', 'inscripciones'])
            ->get()
            ->map(function (Curso $curso) {
                $atrasos = 0;
                $tareasVencidas = $curso->tareas->filter(fn ($t) => $t->estaVencida());
                foreach ($curso->inscripciones as $inscripcion) {
                    foreach ($tareasVencidas as $tarea) {
                        if (!$tarea->entregas->contains('estudiante_id', $inscripcion->estudiante_id)) {
                            $atrasos++;
                        }
                    }
                }
                return ['curso' => $curso, 'atrasos' => $atrasos];
            })
            ->filter(fn ($fila) => $fila['atrasos'] > 0)
            ->sortByDesc('atrasos')
            ->take($limite)
            ->values();
    }

    protected function metricasEsquema(): array
    {
        $totalCursos       = Curso::count();
        $cursosConEsquema  = EsquemaEvaluacion::count();
        $cursosSinEsquema  = $totalCursos - $cursosConEsquema;

        // Tasa de aprobación global basada en notas_rubro
        $notaMinima = config('app.nota_minima_aprobatoria', 10.5);
        $totalNotas = NotaRubro::count();
        $aprobados  = NotaRubro::where('nota', '>=', $notaMinima)->count();

        // Distribución de notas por rango
        $distribucion = [
            '18-20' => NotaRubro::whereBetween('nota', [18, 20])->count(),
            '14-17' => NotaRubro::whereBetween('nota', [14, 17.9])->count(),
            '10-13' => NotaRubro::whereBetween('nota', [10, 13.9])->count(),
            '0-9'   => NotaRubro::where('nota', '<', 10)->count(),
        ];

        // Auditoría reciente
        $auditoriasRecientes = BitacoraAuditoria::with(['ejecutadoPor', 'curso'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'cursos_con_esquema'  => $cursosConEsquema,
            'cursos_sin_esquema'  => $cursosSinEsquema,
            'total_notas_rubros'  => $totalNotas,
            'tasa_aprobacion_rubros' => $totalNotas > 0
                ? round(($aprobados / $totalNotas) * 100)
                : null,
            'distribucion_notas'  => $distribucion,
            'auditorias_recientes' => $auditoriasRecientes,
        ];
    }

    public function render()
    {
        $usuariosPorRol = collect(Rol::cases())->mapWithKeys(fn (Rol $rol) => [
            $rol->value => User::role($rol->value)->count(),
        ]);

        // Usuarios activos vs desactivados (ERS RF-15)
        $usuariosActivos    = User::where('activo', true)->count();
        $usuariosInactivos  = User::where('activo', false)->count();
        $usuariosPrimerLogin = User::where('primer_login', true)->count();

        $usoBot = [
            'conversaciones'       => ConversacionBot::count(),
            'mensajes'             => ConversacionBot::get()->sum(fn ($c) => count($c->mensajes ?? [])),
            'propuestas_pendientes' => PropuestaBot::where('estado', 'pendiente')->count(),
            'propuestas_aprobadas'  => PropuestaBot::where('estado', 'aprobado')->count(),
            'propuestas_rechazadas' => PropuestaBot::where('estado', 'rechazado')->count(),
        ];

        return view('livewire.panel-admin.metricas', [
            'usuariosPorRol'      => $usuariosPorRol,
            'usuariosActivos'     => $usuariosActivos,
            'usuariosInactivos'   => $usuariosInactivos,
            'usuariosPrimerLogin' => $usuariosPrimerLogin,
            'totalCursos'         => Curso::count(),
            'totalInscripciones'  => Inscripcion::where('activo_local', true)->count(),
            'cursosConMasAtraso'  => $this->cursosConMasAtraso(),
            'usoBot'              => $usoBot,
            'esquema'             => $this->metricasEsquema(),
        ]);
    }
}
