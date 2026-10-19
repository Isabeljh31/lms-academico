<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Leccion;
use App\Models\LeccionCompletada;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * ERS RF-36 y RF-67: abre el video o el material de una lección. Si el
 * Profesor definió que la lección se completa "al abrir su recurso", la
 * apertura queda registrada como lección completada del estudiante.
 */
class RecursoLeccionController extends Controller
{
    public function __invoke(Request $request, Curso $curso, Leccion $leccion, string $recurso): RedirectResponse
    {
        Gate::authorize('ver', $curso);
        abort_unless($leccion->modulo?->curso_id === $curso->id, 404);

        $usuario = $request->user();
        $esEstudiante = $usuario->hasRole('estudiante');

        if ($esEstudiante) {
            abort_unless($leccion->esVisibleParaEstudiante() && $leccion->modulo->esVisibleParaEstudiante(), 404);
        }

        // ERS RNF-06: un material subido se abre con un enlace temporal; un enlace externo, tal cual.
        $url = $recurso === 'video' ? $leccion->video_url : SubidorArchivos::enlace($leccion->material_url);
        abort_unless($url, 404);

        if ($esEstudiante && $leccion->seCompletaAlAbrir()) {
            LeccionCompletada::firstOrCreate(
                ['leccion_id' => $leccion->id, 'estudiante_id' => $usuario->id],
                ['completada_en' => now()],
            );
        }

        return redirect()->away($url);
    }
}
