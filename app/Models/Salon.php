<?php

namespace App\Models;

use Database\Factories\SalonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Salón de una sede, con su tipo y su capacidad. Las clases que se dictan en
 * él lo referencian en sesiones_clase.salon_id; la descripción de la clase
 * conserva además la línea «Aula: CÓDIGO». La disponibilidad la calcula
 * App\Services\Asistencia\DisponibilidadSalones.
 */
class Salon extends Model
{
    /** @use HasFactory<SalonFactory> */
    use HasFactory;

    protected $table = 'salones';

    protected $fillable = ['sede_id', 'codigo', 'nombre', 'tipo_salon_id', 'capacidad', 'equipamiento', 'activo'];

    protected $attributes = ['activo' => true];

    protected function casts(): array
    {
        return [
            'capacidad' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoSalon::class, 'tipo_salon_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionClase::class);
    }

    /** @param  Builder<Salon>  $query */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
