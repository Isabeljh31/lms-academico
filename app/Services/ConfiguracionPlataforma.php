<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Configuración general de la plataforma (Módulo 8). No crea una tabla
 * nueva —el documento del proyecto lo pide explícitamente para este
 * módulo—, así que se persiste en un archivo JSON pequeño en el disco
 * local, no en la tabla `cache`: spatie/laravel-permission invalida su
 * propio caché de roles/permisos con un `flush()` completo del store
 * cuando el driver no soporta tags (p.ej. `array`/`database`), lo que
 * borraría cualquier otra clave guardada ahí, incluida esta.
 */
class ConfiguracionPlataforma
{
    protected const ARCHIVO = 'configuracion-plataforma.json';

    public function botActivo(): bool
    {
        return (bool) ($this->leer()['bot_activo'] ?? true);
    }

    public function establecerBotActivo(bool $activo): void
    {
        $this->escribir(['bot_activo' => $activo]);
    }

    protected function leer(): array
    {
        if (! Storage::exists(self::ARCHIVO)) {
            return [];
        }

        return json_decode(Storage::get(self::ARCHIVO), associative: true) ?? [];
    }

    protected function escribir(array $datos): void
    {
        Storage::put(self::ARCHIVO, json_encode([...$this->leer(), ...$datos]));
    }
}
