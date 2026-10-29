<?php

namespace App\Http\Controllers;

use App\Livewire\Cursos\Show;
use App\Models\Modulo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Compatibilidad: la antigua pantalla "Lecciones" de una semana. El contenido
 * ahora se gestiona en la pantalla del curso; esta ruta lleva allí con la
 * semana (o las prácticas de campo) ya desplegada.
 */
class LeccionesSemanaController extends Controller
{
    public function __invoke(Modulo $modulo): RedirectResponse
    {
        Gate::authorize('gestionar', $modulo->curso);

        return redirect()->route('cursos.show', [
            'curso' => $modulo->curso_id,
            'semana' => $modulo->semana ?? Show::CLAVE_PRACTICAS,
        ]);
    }
}
