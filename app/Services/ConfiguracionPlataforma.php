<?php

namespace App\Services;

use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración general de la plataforma (ERS 3.18, RF-107 y RF-108).
 *
 * Se persiste en la tabla `configuraciones_plataforma` (clave/valor). Antes
 * se guardaba en un JSON en storage/, que se pierde en cada despliegue en
 * contenedores efímeros; ese archivo se sigue leyendo como respaldo para no
 * perder un valor guardado previamente. No se usa la tabla `cache` porque
 * spatie/laravel-permission hace flush completo del store al invalidar roles.
 */
class ConfiguracionPlataforma
{
    protected const ARCHIVO_ANTERIOR = 'configuracion-plataforma.json';

    /**
     * Valores por defecto de cada parámetro.
     *
     * @var array<string, mixed>
     */
    public const PREDETERMINADOS = [
        'bot_activo' => true,
        'nota_minima_aprobatoria' => 10.5,
        'nombre_institucion' => 'LearnUp',
        'zona_horaria' => 'America/Lima',
        'formato_fecha' => 'd/m/Y',
    ];

    /**
     * Formatos de fecha que el Supervisor puede elegir (RF-108).
     *
     * @var array<string, string>
     */
    public const FORMATOS_FECHA = [
        'd/m/Y' => 'dd/mm/aaaa (26/09/2026)',
        'd-m-Y' => 'dd-mm-aaaa (26-09-2026)',
        'Y-m-d' => 'aaaa-mm-dd (2026-09-26)',
    ];

    /** @var array<string, mixed>|null */
    protected ?array $valores = null;

    public function botActivo(): bool
    {
        return (bool) $this->obtener('bot_activo');
    }

    public function establecerBotActivo(bool $activo): void
    {
        $this->guardar(['bot_activo' => $activo]);
    }

    /** ERS RF-107: nota mínima aprobatoria general (escala 0–20). */
    public function notaMinimaAprobatoria(): float
    {
        return (float) $this->obtener('nota_minima_aprobatoria');
    }

    /** ERS RF-108: zona horaria en la que opera toda la plataforma. */
    public function zonaHoraria(): string
    {
        $zona = (string) $this->obtener('zona_horaria');

        return in_array($zona, DateTimeZone::listIdentifiers(), true) ? $zona : self::PREDETERMINADOS['zona_horaria'];
    }

    /**
     * ERS RF-108: aplica la zona horaria configurada al proceso actual, de modo
     * que now(), las fechas que se guardan y la comparación de plazos usen la
     * hora de la institución.
     */
    public function aplicarZonaHoraria(): void
    {
        $zona = $this->zonaHoraria();

        config(['app.timezone' => $zona]);
        date_default_timezone_set($zona);
    }

    /** ERS RF-108: formato de fecha elegido (d/m/Y, d-m-Y o Y-m-d). */
    public function formatoFecha(): string
    {
        $formato = (string) $this->obtener('formato_fecha');

        return array_key_exists($formato, self::FORMATOS_FECHA) ? $formato : self::PREDETERMINADOS['formato_fecha'];
    }

    public function formatoFechaHora(bool $conSegundos = false): string
    {
        return $this->formatoFecha().($conSegundos ? ' H:i:s' : ' H:i');
    }

    /** Día y mes sin año, en el orden del formato elegido (d/m, d-m o m-d). */
    public function formatoDiaMes(): string
    {
        return match ($this->formatoFecha()) {
            'Y-m-d' => 'm-d',
            'd-m-Y' => 'd-m',
            default => 'd/m',
        };
    }

    /** Código de formato equivalente para las celdas de fecha y hora de Excel. */
    public function formatoFechaHoraExcel(): string
    {
        return strtr($this->formatoFecha(), ['d' => 'dd', 'm' => 'mm', 'Y' => 'yyyy']).' hh:mm';
    }

    public function obtener(string $clave): mixed
    {
        return $this->todos()[$clave] ?? self::PREDETERMINADOS[$clave] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function todos(): array
    {
        if ($this->valores !== null) {
            return $this->valores;
        }

        try {
            $guardados = DB::table('configuraciones_plataforma')
                ->pluck('valor', 'clave')
                ->map(fn (?string $valor) => json_decode((string) $valor, associative: true))
                ->all();
        } catch (QueryException) {
            // Tabla aún no creada (instalación nueva o migraciones en curso): valores por defecto, sin memorizar.
            return [...self::PREDETERMINADOS, ...$this->leerArchivoAnterior()];
        }

        return $this->valores = [...self::PREDETERMINADOS, ...$this->leerArchivoAnterior(), ...$guardados];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardar(array $datos): void
    {
        foreach ($datos as $clave => $valor) {
            DB::table('configuraciones_plataforma')->updateOrInsert(
                ['clave' => $clave],
                ['valor' => json_encode($valor), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $this->valores = null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function leerArchivoAnterior(): array
    {
        if (! Storage::exists(self::ARCHIVO_ANTERIOR)) {
            return [];
        }

        return json_decode(Storage::get(self::ARCHIVO_ANTERIOR), associative: true) ?? [];
    }
}
