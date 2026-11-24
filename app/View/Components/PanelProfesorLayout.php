<?php

namespace App\View\Components;

use App\Models\User;
use App\Services\Reportes\PanelProfesor;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Layout propio del Profesor (Panel, Cursos y detalle del curso): sin barra
 * lateral ni encabezado, con la navegación en una barra inferior flotante.
 * Las demás pantallas siguen usando AppLayout (layouts/app.blade.php), que no
 * se modifica.
 */
class PanelProfesorLayout extends Component
{
    public function __construct(protected PanelProfesor $panel) {}

    /**
     * ¿El usuario ve las pantallas de Cursos rediseñadas del Profesor (vista y
     * datos propios de ese rol)? Solo un profesor "puro": quien además es
     * Supervisor o Estudiante conserva las pantallas compartidas. No cambiar
     * este criterio sin revisar los 3 usos existentes (Compartido\Cursos\Index,
     * Compartido\Cursos\Show, Compartido\Asistencia\Index), que asumen que
     * `true` implica datos de Profesor. Para "¿usa el layout nuevo (mismo
     * diseño para Profesor y Estudiante)?" usar {@see usaLayoutCompartido()}.
     */
    public static function aplicaA(?User $usuario): bool
    {
        return $usuario !== null
            && $usuario->hasRole('profesor')
            && ! $usuario->hasAnyRole(['supervisor', 'estudiante']);
    }

    /**
     * ¿El usuario ve el layout compartido (Panel, barra superior) con el
     * diseño nuevo? Profesor o Estudiante "puros". No implica qué datos o
     * vista de Cursos usar dentro de ese layout, para eso ver aplicaA().
     */
    public static function usaLayoutCompartido(?User $usuario): bool
    {
        if ($usuario === null || $usuario->hasRole('supervisor')) {
            return false;
        }

        return $usuario->hasRole('profesor') xor $usuario->hasRole('estudiante');
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.panel-profesor', [
            'porCalificar' => $this->panel->totalPorCalificar(auth()->user()),
        ]);
    }
}
