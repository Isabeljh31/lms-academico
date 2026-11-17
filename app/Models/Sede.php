<?php

namespace App\Models;

use Database\Factories\SedeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sede de la institución (extensión fuera del ERS: reprogramación de clases
 * con salones reales). Catálogo que administra el Supervisor.
 */
class Sede extends Model
{
    /** @use HasFactory<SedeFactory> */
    use HasFactory;

    protected $table = 'sedes';

    protected $fillable = ['nombre', 'codigo', 'activa'];

    protected $attributes = ['activa' => true];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function salones(): HasMany
    {
        return $this->hasMany(Salon::class);
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }
}
