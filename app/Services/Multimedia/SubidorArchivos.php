<?php

namespace App\Services\Multimedia;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sube fotos de perfil, portadas de curso, materiales y entregas.
 *
 * Si CLOUDINARY_URL está configurada, sube a Cloudinary y devuelve la URL
 * segura (https). Si no, cae automáticamente al disco local "public"
 * (storage/app/public) para que el sistema funcione igual sin la
 * credencial real. Ver ARQUITECTURA.md § Cloudinary.
 */
class SubidorArchivos
{
    public function activo(): bool
    {
        return filled(config('services.cloudinary.url'));
    }

    /**
     * Sube un archivo dentro de una carpeta lógica (p.ej. "avatars",
     * "cursos/portadas", "entregas") y devuelve la URL pública final.
     */
    public function subir(UploadedFile $archivo, string $carpeta): string
    {
        if ($this->activo()) {
            return $this->subirACloudinary($archivo, $carpeta);
        }

        return $this->subirLocal($archivo, $carpeta);
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

    protected function subirLocal(UploadedFile $archivo, string $carpeta): string
    {
        $nombre = Str::uuid().'.'.$archivo->getClientOriginalExtension();

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
