<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Información del curso": carpeta administrativa que arma el Supervisor
 * (sílabo, actas, enlaces útiles), separada del contenido semanal del
 * docente. Ver migración create_carpetas_curso_table.
 */
class CarpetaCurso extends Model
{
    protected $table = 'carpetas_curso';

    protected $fillable = [
        'curso_id',
        'nombre',
        'orden',
        'recursos',
    ];

    protected function casts(): array
    {
        return [
            'recursos' => 'array',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }
}
