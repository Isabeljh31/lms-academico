<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Services\Reportes\ProgresoAcademicoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class BoletaPdfController extends Controller
{
    public function __invoke(Curso $curso, ProgresoAcademicoService $servicio)
    {
        Gate::authorize('verProgreso', $curso);

        $estudiante = auth()->user();
        $progreso = $servicio->progresoEstudiante($estudiante, $curso);

        $pdf = Pdf::loadView('reportes.boleta-pdf', [
            'estudiante' => $estudiante,
            'progreso' => $progreso,
        ]);

        return $pdf->stream("boleta-{$curso->id}.pdf");
    }
}
