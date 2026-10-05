<?php

namespace App\Services\Multimedia;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sube fotos de perfil, portadas de curso, materiales, entregas y adjuntos.
 *
 * Si CLOUDINARY_URL está configurada, sube a Cloudinary y devuelve la URL
 * segura (https). Si no, cae automáticamente al disco local "public"
 * (storage/app/public) para que el sistema funcione igual sin la
 * credencial real. Ver ARQUITECTURA.md § Cloudinary.
 *
 * Seguridad: solo acepta extensiones de la lista blanca de
 * config/archivos.php (nunca scripts ejecutables), el nombre final lo genera
 * el servidor y el tamaño máximo es único para todo el sistema (10 MB por el
 * límite del plan gratuito de Cloudinary). Cualquier fallo de subida se
 * convierte en un error de validación legible en vez de un error 500.
 */
class SubidorArchivos
{
    public function activo(): bool
    {
        return filled(config('services.cloudinary.url'));
    }

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
     * Sube un archivo dentro de una carpeta lógica (p.ej. "entregas") y
     * devuelve la URL final.
     *
     * @param  array<int, string>|null  $extensiones
     *
     * @throws ValidationException si el archivo no es válido o el proveedor lo rechaza.
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
            return $this->activo()
                ? $this->subirACloudinary($archivo, $carpeta)
                : $this->subirLocal($archivo, $carpeta, $extension);
        } catch (Throwable $e) {
            Log::warning('SubidorArchivos: no se pudo subir el archivo', [
                'carpeta' => $carpeta,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $campo => 'No se pudo subir el archivo. Verifica que no supere los '
                    .(int) round(config('archivos.max_kb') / 1024).' MB e inténtalo de nuevo.',
            ]);
        }
    }

    protected function subirACloudinary(UploadedFile $archivo, string $carpeta): string
    {
        $cloudinary = new Cloudinary(config('services.cloudinary.url'));

        $resultado = $cloudinary->uploadApi()->upload($archivo->getRealPath(), [
            'folder' => 'lms-academico/'.$carpeta,
            'resource_type' => 'auto',
        ]);

        return $resultado['secure_url'];
    }

    protected function subirLocal(UploadedFile $archivo, string $carpeta, string $extension): string
    {
        $nombre = Str::uuid().'.'.$extension;

        $archivo->storeAs($carpeta, $nombre, 'public');

        return Storage::disk('public')->url($carpeta.'/'.$nombre);
    }

    /**
     * Borra un archivo previamente subido por este servicio (sea Cloudinary
     * o disco local), a partir de su URL. Silencioso si no lo encuentra.
     */
    public function eliminar(?string $url): void
    {
        if (blank($url)) {
            return;
        }

        if ($this->activo() && str_contains($url, 'cloudinary.com')) {
            $this->eliminarDeCloudinary($url);

            return;
        }

        $ruta = Str::after($url, Storage::disk('public')->url(''));

        if ($ruta !== $url) {
            Storage::disk('public')->delete($ruta);
        }
    }

    protected function eliminarDeCloudinary(string $url): void
    {
        // El public_id de Cloudinary es la ruta sin extensión, después de "/upload/v.../".
        if (! preg_match('#/upload/(?:v\d+/)?(.+)\.\w+$#', $url, $coincidencias)) {
            return;
        }

        $cloudinary = new Cloudinary(config('services.cloudinary.url'));
        $cloudinary->uploadApi()->destroy($coincidencias[1]);
    }
}
