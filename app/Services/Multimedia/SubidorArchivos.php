<?php

namespace App\Services\Multimedia;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Sube fotos de perfil, portadas de curso, materiales, entregas y adjuntos.
 *
 * ERS RNF-06: los archivos van a un almacenamiento privado (Supabase Storage
 * en el equipo, storage/app/private sin credenciales y en las pruebas; ver
 * config/archivos.php). En la base de datos se guarda la RUTA del archivo,
 * nunca una URL pública: para mostrarlo, enlace() genera un enlace firmado
 * que vence a los pocos minutos.
 *
 * Seguridad: solo acepta extensiones de la lista blanca de
 * config/archivos.php (nunca scripts ejecutables), el nombre final lo genera
 * el servidor y el tamaño máximo es único para todo el sistema (10 MB).
 * Cualquier fallo de subida se convierte en un error de validación legible
 * en vez de un error 500.
 */
class SubidorArchivos
{
    /**
     * Reglas de validación comunes para un campo de archivo.
     *
     * @param  array<int, string>|null  $extensiones  Subconjunto permitido; por defecto, "documentos".
     * @return array<int, string>
     */
    public static function reglas(?array $extensiones = null, bool $requerido = true): array
    {
        $extensiones = self::extensionesPermitidas($extensiones);

        return [
            $requerido ? 'required' : 'nullable',
            'file',
            'max:'.config('archivos.max_kb'),
            'extensions:'.implode(',', $extensiones),
        ];
    }

    /**
     * Mensajes de validación en español para las reglas de reglas().
     *
     * @return array<string, string>
     */
    public static function mensajes(string $campo): array
    {
        $megas = (int) round(config('archivos.max_kb') / 1024);

        return [
            "{$campo}.max" => "El archivo supera el tamaño máximo permitido de {$megas} MB.",
            "{$campo}.extensions" => 'El tipo de archivo no está permitido.',
            "{$campo}.required" => 'Selecciona un archivo.',
            "{$campo}.file" => 'No se pudo leer el archivo seleccionado.',
        ];
    }

    /**
     * @param  array<int, string>|null  $extensiones
     * @return array<int, string>
     */
    public static function extensionesPermitidas(?array $extensiones = null): array
    {
        $base = config('archivos.extensiones.documentos');

        if (empty($extensiones)) {
            return $base;
        }

        return array_values(array_intersect(array_map('strtolower', $extensiones), $base));
    }

    /**
     * Guarda el archivo dentro de una carpeta lógica (p. ej. "entregas") con
     * un nombre generado por el servidor y devuelve su ruta (p. ej.
     * "entregas/9b2f….pdf"), que es lo que se guarda en la base de datos.
     *
     * @param  array<int, string>|null  $extensiones
     *
     * @throws ValidationException si el archivo no es válido o el almacenamiento lo rechaza.
     */
    public function subir(UploadedFile $archivo, string $carpeta, string $campo = 'archivo', ?array $extensiones = null): string
    {
        $extension = strtolower($archivo->getClientOriginalExtension());

        if (! in_array($extension, self::extensionesPermitidas($extensiones), true)) {
            throw ValidationException::withMessages([$campo => 'El tipo de archivo no está permitido.']);
        }

        if ($archivo->getSize() > config('archivos.max_kb') * 1024) {
            throw ValidationException::withMessages([$campo => self::mensajes($campo)["{$campo}.max"]]);
        }

        try {
            return $this->guardar($archivo, trim($carpeta, '/'), Str::uuid().'.'.$extension);
        } catch (Throwable $e) {
            Log::warning('SubidorArchivos: no se pudo subir el archivo', [
                'carpeta' => $carpeta,
                'disco' => config('archivos.disco'),
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $campo => 'No se pudo subir el archivo. Verifica que no supere los '
                    .(int) round(config('archivos.max_kb') / 1024).' MB e inténtalo de nuevo.',
            ]);
        }
    }

    protected function guardar(UploadedFile $archivo, string $carpeta, string $nombre): string
    {
        $ruta = self::disco()->putFileAs($carpeta, $archivo, $nombre);

        if ($ruta === false) {
            throw new RuntimeException('El almacenamiento no aceptó el archivo.');
        }

        return $ruta;
    }

    /**
     * Borra un archivo subido por este servicio. Los enlaces externos (p. ej.
     * un material en Google Drive) y los valores vacíos se ignoran.
     */
    public function eliminar(?string $valor): void
    {
        if (blank($valor) || self::esEnlaceExterno($valor)) {
            return;
        }

        try {
            self::disco()->delete($valor);
        } catch (Throwable $e) {
            Log::warning('SubidorArchivos: no se pudo borrar el archivo', ['ruta' => $valor, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Enlace para abrir un archivo guardado: si es una ruta del almacenamiento,
     * un enlace firmado que vence en config('archivos.minutos_enlace') minutos;
     * si ya es un enlace externo (material en Drive, datos antiguos), el mismo.
     * Debe llamarse solo en pantallas que ya verificaron el permiso de acceso.
     */
    public static function enlace(?string $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        if (self::esEnlaceExterno($valor)) {
            return $valor;
        }

        try {
            return self::disco()->temporaryUrl($valor, now()->addMinutes(config('archivos.minutos_enlace')));
        } catch (Throwable $e) {
            Log::warning('SubidorArchivos: no se pudo generar el enlace temporal', ['ruta' => $valor, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public static function esEnlaceExterno(string $valor): bool
    {
        return (bool) preg_match('#^https?://#i', $valor);
    }

    protected static function disco(): Filesystem
    {
        return Storage::disk(config('archivos.disco'));
    }
}
