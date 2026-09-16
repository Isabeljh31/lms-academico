<?php

namespace App\Livewire\PanelAdmin;

use App\Enums\Rol;
use App\Models\ConversacionBot;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\PropuestaBot;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Métricas globales del Módulo 8. No crea tablas nuevas: consulta los
 * módulos 1 (usuarios), 2/3 (cursos y tareas) y 7 (uso del bot).
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
                        $entrego = $tarea->entregas->contains('estudiante_id', $inscripcion->estudiante_id);

                        if (! $entrego) {
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

    public function render()
    {
        $usuariosPorRol = collect(Rol::cases())->mapWithKeys(fn (Rol $rol) => [
            $rol->value => User::role($rol->value)->count(),
        ]);

        $usoBot = [
            'conversaciones' => ConversacionBot::count(),
            'mensajes' => ConversacionBot::get()->sum(fn ($c) => count($c->mensajes ?? [])),
            'propuestas_pendientes' => PropuestaBot::where('estado', 'pendiente')->count(),
            'propuestas_aprobadas' => PropuestaBot::where('estado', 'aprobado')->count(),
            'propuestas_rechazadas' => PropuestaBot::where('estado', 'rechazado')->count(),
        ];

        return view('livewire.panel-admin.metricas', [
            'usuariosPorRol' => $usuariosPorRol,
            'totalCursos' => Curso::count(),
            'totalInscripciones' => Inscripcion::count(),
            'cursosConMasAtraso' => $this->cursosConMasAtraso(),
            'usoBot' => $usoBot,
        ]);
    }
}
