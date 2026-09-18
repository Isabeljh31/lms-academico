<?php

namespace App\Services\Bot;

use App\Models\Curso;
use App\Services\Bot\Concerns\ConstruyeContextoIA;
use App\Services\Bot\Contracts\ProveedorIA;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente Guzzle (vía Http::) contra la API de Claude (Anthropic) para el
 * bot de sugerencias (Módulos 6-7). Sin ANTHROPIC_API_KEY corre en modo
 * simulado (mock), claramente señalado, para poder probar el flujo
 * completo sin gastar créditos ni depender de la key real.
 *
 * Proveedor de pago; el proveedor por defecto durante desarrollo es
 * App\Services\Bot\GroqProveedor (gratuito). Ver IA_PROVEEDOR en
 * ARQUITECTURA.md § Módulo 6/7 — proveedor de IA.
 */
class ClaudeBotService implements ProveedorIA
{
    use ConstruyeContextoIA;

    public function activo(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Responde una pregunta de FAQ del estudiante/profesor, con contexto
     * del curso actual si aplica.
     *
     * @param  array<int, array{rol: string, contenido: string}>  $historial
     */
    public function responder(string $pregunta, ?Curso $curso, array $historial = []): string
    {
        if (! $this->activo()) {
            return $this->respuestaSimulada($pregunta, $curso, 'ANTHROPIC_API_KEY');
        }

        $sistema = $this->promptSistema($curso);

        $mensajes = collect($historial)
            ->map(fn ($m) => ['role' => $m['rol'] === 'usuario' ? 'user' : 'assistant', 'content' => $m['contenido']])
            ->push(['role' => 'user', 'content' => $pregunta])
            ->all();

        try {
            $respuesta = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 512,
                'system' => $sistema,
                'messages' => $mensajes,
            ]);

            if ($respuesta->failed()) {
                Log::warning('ClaudeBotService: respuesta fallida de Anthropic', ['status' => $respuesta->status()]);

                return 'No pude conectarme con el asistente en este momento. Intenta de nuevo en unos minutos.';
            }

            return $respuesta->json('content.0.text') ?? 'No obtuve una respuesta del asistente.';
        } catch (\Throwable $e) {
            Log::error('ClaudeBotService: excepción llamando a Anthropic', ['mensaje' => $e->getMessage()]);

            return 'Ocurrió un error al consultar al asistente. Intenta de nuevo más tarde.';
        }
    }
}
