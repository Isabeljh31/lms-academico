<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Nombres o apellidos de una persona: solo letras (con tildes y ñ), separadas
 * por un espacio, guion o apóstrofe. Acepta "María José", "O'Brien" o
 * "Pérez-Salinas"; rechaza números y símbolos como "12345" o "<b>".
 *
 * Se usa en todas las vías de alta y edición de cuentas: formulario,
 * directorio de profesores, importación masiva y API.
 */
class NombrePersona implements ValidationRule
{
    public const MINIMO = 2;

    public const MAXIMO = 60;

    protected const FORMATO = "/^\p{L}+(?:[ '\-]\p{L}+)*$/u";

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $texto = is_string($value) ? self::normalizar($value) : '';

        if (! preg_match(self::FORMATO, $texto)) {
            $fail('Los :attribute solo pueden tener letras y espacios (también se permite guion y apóstrofe, como en Pérez-Salinas u O\'Brien).');

            return;
        }

        $largo = mb_strlen($texto);

        if ($largo < self::MINIMO || $largo > self::MAXIMO) {
            $fail('Los :attribute deben tener entre '.self::MINIMO.' y '.self::MAXIMO.' caracteres.');
        }
    }

    /**
     * Quita los espacios de los extremos y deja un solo espacio entre palabras.
     */
    public static function normalizar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }
}
