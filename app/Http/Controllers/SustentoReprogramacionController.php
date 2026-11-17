<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCambioClase;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Descarga del archivo de sustento de una reprogramación (extensión fuera
 * del ERS). Solo el Supervisor y el Profesor que pidió la reprogramación
 * (SolicitudCambioClasePolicy::verSustento). El archivo está en el
 * almacenamiento privado: se redirige a un enlace firmado que vence
 * (SubidorArchivos::enlace, config('archivos.minutos_enlace')).
 */
class SustentoReprogramacionController extends Controller
{
    public function __invoke(SolicitudCambioClase $solicitud): RedirectResponse
    {
        Gate::authorize('verSustento', $solicitud);

        $url = SubidorArchivos::enlace($solicitud->sustento_archivo);
        abort_unless($url, 404);

        return redirect()->away($url);
    }
}
