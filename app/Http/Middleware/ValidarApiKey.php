<?php

namespace App\Http\Middleware;

use App\Models\IntegracionExterna;
use App\Models\LogIntegracionApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ERS RF-101: autentica la solicitud por API Key.
 * ERS RF-102: rechaza y registra solicitudes con key inválida o ausente.
 */
class ValidarApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization', '');
        $apiKey = null;

        if (str_starts_with($authHeader, 'Bearer ')) {
            $apiKey = substr($authHeader, 7);
        }

        $integracion = null;

        if ($apiKey) {
            // Buscar por prefijo para evitar iterar sobre todos los hashes
            $prefix = substr($apiKey, 0, 8);
            $candidatos = IntegracionExterna::where('api_key_prefix', $prefix)
                ->where('activo', true)
                ->get();

            foreach ($candidatos as $candidato) {
                if ($candidato->verificarApiKey($apiKey)) {
                    $integracion = $candidato;
                    break;
                }
            }
        }

        // Registrar log (ERS RF-106)
        $logData = [
            'integracion_id'   => $integracion?->id,
            'ip'               => $request->ip(),
            'metodo'           => $request->method(),
            'endpoint'         => $request->path(),
            'status_code'      => $integracion ? 200 : 401,
            'payload_recibido' => $request->all(),
        ];

        if (!$integracion) {
            $logData['error_mensaje'] = 'API Key inválida o ausente';
            LogIntegracionApi::create($logData);

            return response()->json([
                'error' => 'API Key inválida o ausente. Incluya Authorization: Bearer <api_key>',
            ], 401);
        }

        LogIntegracionApi::create($logData);
        $integracion->registrarUso();

        // Adjuntar la integracion al request para el controlador
        $request->merge(['_integracion' => $integracion]);
        $request->attributes->set('integracion', $integracion);

        return $next($request);
    }
}
