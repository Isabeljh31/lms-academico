<?php

namespace App\Enums;

enum Rol: string
{
    case Supervisor = 'supervisor';
    case Profesor = 'profesor';
    case Estudiante = 'estudiante';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Supervisor => 'Supervisor',
            self::Profesor => 'Profesor',
            self::Estudiante => 'Estudiante',
        };
    }
}
