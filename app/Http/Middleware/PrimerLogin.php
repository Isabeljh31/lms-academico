<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ERS RF-05: si el usuario aún tiene primer_login = true,
 * lo redirige obligatoriamente a la vista de cambio de contraseña.
 * Bloquea el acceso al resto de la app hasta que complete el cambio.
 */
class PrimerLogin
{
    /** Rutas permitidas aunque primer_login sea true */
    private array $rutasPermitidas = [
        'primer-login',
        'primer-login.guardar',
        'logout',
        'livewire.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->primer_login) {
            // Permitir la propia ruta de cambio y las llamadas de Livewire
            if (!$request->routeIs($this->rutasPermitidas) && !str_starts_with($request->path(), 'livewire/')) {
                return redirect()->route('primer-login');
            }
        }

        return $next($request);
    }
}
