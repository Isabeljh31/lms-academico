<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ERS RF-70: Asistencia por Sesión y Estudiante.
 * Estados: presente | ausente | tardanza | justificado
 */
class Asistencia extends Model
{
    protected $fillable = [
        'sesion_id', 'estudiante_id', 'estado',
        'observacion', 'registrado_por',
    ];

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(SesionClase::class, 'sesion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function badge(): array
    {
        return match ($this->estado) {
            'presente'    => ['label' => 'Presente',    'color' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'],
            'tardanza'    => ['label' => 'Tardanza',    'color' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
            'justificado' => ['label' => 'Justificado', 'color' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'],
            default       => ['label' => 'Ausente',     'color' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'],
        };
    }
}
