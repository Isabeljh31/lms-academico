<?php

namespace App\Services\Bot;

use App\Models\Curso;
use App\Services\Bot\Concerns\ConstruyeContextoIA;
use App\Services\Bot\Contracts\ProveedorIA;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente Guzzle (vía Http::) contra la API de Groq
 * (https://api.groq.com/openai/v1/chat/completions, compatible con el
 * formato de OpenAI) para el bot de sugerencias (Módulos 6-7). Es el
 * proveedor por defecto durante desarrollo/pruebas del curso porque Groq
 * ofrece un tier gratuito real (sin tarjeta) con modelos open-source
 * (Llama 3.3 70B). Sin GROQ_API_KEY corre en modo simulado, igual que
 * App\Services\Bot\ClaudeBotService. Ver IA_PROVEEDOR en
 * ARQUITECTURA.md § Módulo 6/7 — proveedor de IA.
 */
class GroqProveedor implements ProveedorIA
{
    use ConstruyeContextoIA;

    public function activo(): bool
    {
        return filled(config('services.groq.key'));
    }

    /**
     * @param  array<int, array{rol: string, contenido: string}>  $historial
     */
    public function responder(string $pregunta, ?Curso $curso, array $historial = []): string
    {
        if (! $this->activo()) {
            return $this->respuestaSimulada($pregunta, $curso, 'GROQ_API_KEY');
        }

        $sistema = $this->promptSistema($curso);

        $mensajes = collect($historial)
            ->map(fn ($m) => ['role' => $m['rol'] === 'usuario' ? 'user' : 'assistant', 'content' => $m['contenido']])
            ->push(['role' => 'user', 'content' => $pregunta])
            ->prepend(['role' => 'system', 'content' => $sistema])
            ->all();

        try {
            $respuesta = Http::withToken(config('services.groq.key'))
                ->timeout(20)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config('services.groq.model'),
                    'max_tokens' => 512,
                    'messages' => $mensajes,
                ]);

            if ($respuesta->failed()) {
                Log::warning('GroqProveedor: respuesta fallida de Groq', ['status' => $respuesta->status()]);

                return 'No pude conectarme con el asistente en este momento. Intenta de nuevo en unos minutos.';
            }

            return $respuesta->json('choices.0.message.content') ?? 'No obtuve una respuesta del asistente.';
        } catch (\Throwable $e) {
            Log::error('GroqProveedor: excepción llamando a Groq', ['mensaje' => $e->getMessage()]);

            return 'Ocurrió un error al consultar al asistente. Intenta de nuevo más tarde.';
        }
    }
}
