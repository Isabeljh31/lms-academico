<?php

namespace App\Enums;

enum Rol: string
{
    case Administrador = 'administrador';
    case Profesor = 'profesor';
    case Estudiante = 'estudiante';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Profesor => 'Profesor',
            self::Estudiante => 'Estudiante',
        };
    }
}
