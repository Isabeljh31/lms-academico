<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ERS RF-15: si el Supervisor desactiva una cuenta mientras el usuario tiene
 * una sesión abierta, la sesión se cierra en su siguiente petición.
 */
class CuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['form.email' => 'Esta cuenta ha sido desactivada. Contacta al Supervisor.']);
        }

        return $next($request);
    }
}
