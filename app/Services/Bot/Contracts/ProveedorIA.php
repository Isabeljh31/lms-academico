<?php

namespace App\Services\Bot\Contracts;

use App\Models\Curso;

/**
 * Contrato común para los proveedores de IA del bot de sugerencias
 * (Módulos 6-7). Permite intercambiar el proveedor (Groq, Claude, ...)
 * detrás de una sola interfaz sin tocar App\Livewire\BotSugerencias\Widget.
 */
interface ProveedorIA
{
    public function activo(): bool;

    /**
     * @param  array<int, array{rol: string, contenido: string}>  $historial
     */
    public function responder(string $pregunta, ?Curso $curso, array $historial = []): string;
}
