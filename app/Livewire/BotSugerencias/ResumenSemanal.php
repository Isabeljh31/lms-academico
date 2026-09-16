<?php

namespace App\Livewire\BotSugerencias;

use App\Models\PropuestaBot;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Resumen ejecutivo semanal para el profesor (Módulo 7): visibilidad de
 * las propuestas del bot para los estudiantes de sus cursos. El profesor
 * no aprueba estas propuestas (son del plan personal del estudiante),
 * solo las supervisa.
 */
#[Layout('layouts.app')]
class ResumenSemanal extends Component
{
    public function render()
    {
        $usuario = auth()->user();

        $estudianteIds = $usuario->cursosComoProfesor()
            ->with('inscripciones')
            ->get()
            ->flatMap->inscripciones
            ->pluck('estudiante_id')
            ->unique();

        $propuestas = PropuestaBot::whereIn('usuario_id', $estudianteIds)
            ->where('created_at', '>=', now()->subWeek())
            ->with('usuario')
            ->get();

        return view('livewire.bot-sugerencias.resumen-semanal', [
            'pendientes' => $propuestas->where('estado', 'pendiente'),
            'aprobadas' => $propuestas->where('estado', 'aprobado'),
            'rechazadas' => $propuestas->where('estado', 'rechazado'),
        ]);
    }
}
