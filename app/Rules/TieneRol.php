<?php

namespace App\Rules;

use App\Enums\Rol;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * El valor debe ser el id de una cuenta con el rol indicado. Se usa en todas
 * las pantallas que asignan un profesor a un curso (crear, editar, clonar),
 * para que un estudiante nunca quede con permisos de profesor.
 */
class TieneRol implements ValidationRule
{
    public function __construct(protected Rol $rol) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rol = mb_strtolower($this->rol->etiqueta());

        if (is_array($value)) {
            $ids = collect($value)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique();

            if ($ids->count() !== count(array_unique($value)) || User::role($this->rol->value)->whereKey($ids)->count() !== $ids->count()) {
                $fail("Todos los usuarios seleccionados deben tener rol de {$rol}.");
            }

            return;
        }

        if (! is_numeric($value) || ! User::role($this->rol->value)->whereKey((int) $value)->exists()) {
            $fail("El usuario seleccionado no tiene rol de {$rol}.");
        }
    }
}
