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
     * ¿El usuario ve las pantallas rediseñadas del Profesor? Solo un profesor
     * "puro": quien además es Supervisor o Estudiante conserva las pantallas
     * compartidas (mismo criterio que dashboard.blade.php).
     */
    public static function aplicaA(?User $usuario): bool
    {
        return $usuario !== null
            && $usuario->hasRole('profesor')
            && ! $usuario->hasAnyRole(['supervisor', 'estudiante']);
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
