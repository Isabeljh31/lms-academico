<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ERS RF-39: Sesión de Clase programada dentro de un Curso.
 * RF-70: Base para el registro de asistencia.
 */
class SesionClase extends Model
{
    protected $table = 'sesiones_clase';

    protected $fillable = [
        'curso_id', 'titulo', 'fecha', 'duracion_minutos',
        'descripcion', 'numero_sesion',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'duracion_minutos' => 'integer',
        'numero_sesion' => 'integer',
    ];

    /** Aviso al intentar pasar lista de una clase que aún no ocurre. */
    public const MENSAJE_CLASE_FUTURA = 'Esta clase aún no ha ocurrido. Podrás pasar lista desde el día de la clase.';

    /**
     * Regla del Profesor: se puede tomar o modificar la asistencia desde las
     * 00:00 del día de la clase (zona horaria de la aplicación). Una clase de
     * un día posterior queda bloqueada. Las clases pasadas se siguen pudiendo
     * corregir (sin ventana de cierre).
     */
    public function permiteTomarLista(?CarbonInterface $ahora = null): bool
    {
        return $this->fecha !== null && $this->fecha->lte(($ahora ?? now())->copy()->endOfDay());
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'sesion_id');
    }

    /**
     * ERS RF-71: % asistencia para esta sesión.
     */
    public function porcentajeAsistencia(): ?float
    {
        $total = $this->asistencias()->count();
        $presentes = $this->asistencias()->whereIn('estado', ['presente', 'tardanza'])->count();

        return $total > 0 ? round($presentes / $total * 100, 1) : null;
    }
}
