<?php

namespace App\Http\Middleware;

use App\Models\IntegracionExterna;
use App\Models\LogIntegracionApi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

/**
 * ERS RF-101: autentica la solicitud por API Key (Authorization: Bearer).
 * ERS RF-102: rechaza (401) y registra solicitudes con key inválida o ausente.
 * ERS RF-106: registra todas las solicitudes, con el código final de la respuesta.
 */
class ValidarApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->bearerToken();
        $integracion = $apiKey ? $this->buscarIntegracion($apiKey) : null;

        $log = LogIntegracionApi::create([
            'integracion_id' => $integracion?->id,
            'ip' => $request->ip(),
            'metodo' => $request->method(),
            'endpoint' => $request->path(),
            'status_code' => $integracion ? 202 : 401,
            'payload_recibido' => Arr::except($request->all(), ['password', 'api_key']),
            'error_mensaje' => $integracion ? null : 'API Key inválida, revocada o ausente.',
        ]);

        if (! $integracion) {
            return response()->json([
                'error' => 'API Key inválida o ausente. Incluya Authorization: Bearer <api_key>.',
            ], 401);
        }

        $integracion->registrarUso();
        $request->attributes->set('integracion', $integracion);

        $respuesta = $next($request);

        $log->update([
            'status_code' => $respuesta->getStatusCode(),
            'error_mensaje' => $respuesta->getStatusCode() >= 400
                ? mb_substr((string) $respuesta->getContent(), 0, 1000)
                : null,
        ]);

        return $respuesta;
    }

    protected function buscarIntegracion(string $apiKey): ?IntegracionExterna
    {
        // Se filtra por prefijo para no verificar el hash de todas las integraciones.
        return IntegracionExterna::where('api_key_prefix', substr($apiKey, 0, 8))
            ->where('activo', true)
            ->get()
            ->first(fn (IntegracionExterna $candidata) => $candidata->verificarApiKey($apiKey));
    }
}
