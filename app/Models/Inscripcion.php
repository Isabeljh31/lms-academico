<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inscripcion extends Model
{
    use HasFactory;

    protected $table = 'inscripciones';

    public $timestamps = false;

    // origen: manual | api
    // estado: activo | inactivo
    protected $fillable = [
        'curso_id',
        'estudiante_id',
        'fecha_inscripcion',
        'origen',
        'estado',
        'activo_local',
        'fecha_baja',
        'dado_de_baja_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inscripcion' => 'datetime',
            'fecha_baja'        => 'datetime',
            'activo_local'      => 'boolean',
        ];
    }

    // ==========================================
    // HELPERS DE BAJA LOGICA (ERS RF-29, RF-30)
    // ==========================================

    public function estaActiva(): bool
    {
        return $this->estado === 'activo' && $this->activo_local;
    }

    /** Baja logica: conserva historial academico, solo marca como inactivo. */
    public function darDeBaja(User $ejecutor): void
    {
        $this->update([
            'estado'          => 'inactivo',
            'activo_local'    => false,
            'fecha_baja'      => now(),
            'dado_de_baja_por' => $ejecutor->id,
        ]);
    }

    /** ERS RF-32: para matriculas de API, el Supervisor solo desactiva el acceso local. */
    public function desactivarAccesoLocal(User $ejecutor): void
    {
        $this->update([
            'activo_local'    => false,
            'fecha_baja'      => now(),
            'dado_de_baja_por' => $ejecutor->id,
        ]);
    }

    public function esDeApiExterna(): bool
    {
        return $this->origen === 'api';
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function dadoDeBajaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dado_de_baja_por');
    }
}
