<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * DNI peruano: exactamente 8 dígitos, solo números. Se guarda como texto
 * para conservar los ceros a la izquierda (01234567).
 */
class Dni implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('El DNI debe tener exactamente 8 dígitos (solo números).');

            return;
        }

        if (! preg_match('/^\d{8}$/', trim((string) $value))) {
            $fail('El DNI debe tener exactamente 8 dígitos (solo números).');
        }
    }
}
