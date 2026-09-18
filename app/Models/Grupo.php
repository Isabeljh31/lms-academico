<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Grupo extends Model
{
    protected $table = 'grupos';

    protected $fillable = [
        'curso_id',
        'nombre',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'grupo_estudiante', 'grupo_id', 'estudiante_id')
            ->withTimestamps();
    }
}
