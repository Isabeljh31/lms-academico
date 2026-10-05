<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ERS RF-66/RF-67: una lección se considera completada cuando el estudiante
 * la marca como vista. Alimenta el porcentaje de avance del curso.
 */
class LeccionCompletada extends Model
{
    protected $table = 'lecciones_completadas';

    public $timestamps = false;

    protected $fillable = [
        'leccion_id',
        'estudiante_id',
        'completada_en',
    ];

    protected function casts(): array
    {
        return ['completada_en' => 'datetime'];
    }

    public function leccion(): BelongsTo
    {
        return $this->belongsTo(Leccion::class, 'leccion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }
}
