<?php

namespace App\Enums;

/**
 * Cómo obtiene su nota (sobre 20) una evaluación de la nota final. Un rubro
 * sin modo (null) todavía no fue configurado por el profesor y se comporta
 * como nota manual.
 */
enum ModoCalculo: string
{
    case Manual = 'manual';
    case Promedio = 'promedio';
    case Examen = 'examen';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Manual => 'Nota manual',
            self::Promedio => 'Promedio de actividades',
            self::Examen => 'Examen',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Manual => 'La escribes en el libro de calificaciones.',
            self::Promedio => 'Combina tareas y quizzes con el peso que elijas.',
            self::Examen => 'Una sola actividad vale el 100 %.',
        };
    }

    public function esAutomatico(): bool
    {
        return $this !== self::Manual;
    }

    /** @return list<string> */
    public static function automaticos(): array
    {
        return [self::Promedio->value, self::Examen->value];
    }
}
