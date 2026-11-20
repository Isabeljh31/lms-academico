<?php

namespace App\Models;

use Database\Factories\FacultadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Facultad de una sede (extensión fuera del ERS). Catálogo que administra el
 * Supervisor en «Sedes, salones y facultades»; cada curso puede tener una.
 * Los salones son de la sede: cualquier facultad de la sede puede usarlos.
 */
class Facultad extends Model
{
    /** @use HasFactory<FacultadFactory> */
    use HasFactory;

    protected $table = 'facultades';

    protected $fillable = ['sede_id', 'nombre', 'codigo', 'activa'];

    protected $attributes = ['activa' => true];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }
}
