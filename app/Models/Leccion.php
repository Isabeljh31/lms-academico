<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ERS RF-35: Lección como sub-entidad dentro del Módulo.
 * RF-36: Puede tener video_url y material_url.
 * RF-37: Estado borrador | publicado | programado.
 */
class Leccion extends Model
{
    protected $table = 'lecciones';

    /**
     * ERS RF-67: condición que define el Profesor para dar la lección por completada.
     *
     * @var array<string, string>
     */
    public const CONDICIONES = [
        'marcar' => 'El estudiante la marca como vista',
        'abrir_recurso' => 'Al abrir su video o su material',
    ];

    /**
     * Tipo de recurso de la lección: define su etiqueta y su ícono en el
     * contenido semanal del curso. No cambia cómo se completa ni se publica.
     *
     * @var array<string, array{etiqueta: string, icono: string}>
     */
    public const TIPOS_RECURSO = [
        'lectura' => ['etiqueta' => 'Lectura', 'icono' => 'libro'],
        'video' => ['etiqueta' => 'Video', 'icono' => 'video'],
        'presentacion' => ['etiqueta' => 'Presentación', 'icono' => 'presentacion'],
        'grabacion' => ['etiqueta' => 'Grabación de sesión', 'icono' => 'grabacion'],
        'foro' => ['etiqueta' => 'Foro de preguntas', 'icono' => 'chat'],
        'actividad' => ['etiqueta' => 'Actividad complementaria', 'icono' => 'lista'],
        'enlace' => ['etiqueta' => 'Recurso externo', 'icono' => 'enlace'],
    ];

    protected $attributes = [
        'condicion_completitud' => 'marcar',
        'tipo_recurso' => 'lectura',
    ];

    protected $fillable = [
        'modulo_id', 'titulo', 'descripcion', 'video_url',
        'material_url', 'material_nombre', 'estado', 'fecha_publicacion', 'orden', 'condicion_completitud',
        'tipo_recurso',
    ];

    /**
     * @return array{etiqueta: string, icono: string}
     */
    public function tipoRecurso(): array
    {
        return self::TIPOS_RECURSO[$this->tipo_recurso] ?? self::TIPOS_RECURSO['lectura'];
    }

    protected $casts = [
        'fecha_publicacion' => 'datetime',
        'orden' => 'integer',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * ERS RF-38: Visible para estudiante solo si está publicada
     * (o programada con fecha pasada).
     */
    public function esVisibleParaEstudiante(): bool
    {
        if ($this->estado === 'publicado') {
            return true;
        }
        if ($this->estado === 'programado' && $this->fecha_publicacion?->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * ERS RF-38: publicadas, o programadas con fecha ya alcanzada. Las dos
     * condiciones van agrupadas para no anular otros filtros de la consulta.
     */
    public function scopePublicadas($query)
    {
        return $query->where(fn ($q) => $q
            ->where('estado', 'publicado')
            ->orWhere(fn ($q) => $q
                ->where('estado', 'programado')
                ->where('fecha_publicacion', '<=', now())
            )
        );
    }

    /** La lección se completa sola al abrir su recurso (y tiene un recurso que abrir). */
    public function seCompletaAlAbrir(): bool
    {
        return $this->condicion_completitud === 'abrir_recurso' && ($this->video_url || $this->material_url);
    }

    public function completadaPor(User $estudiante): bool
    {
        return $this->completadas()->where('estudiante_id', $estudiante->id)->exists();
    }

    /** ERS RF-66/RF-67: estudiantes que marcaron la lección como completada. */
    public function completadas(): HasMany
    {
        return $this->hasMany(LeccionCompletada::class, 'leccion_id');
    }
}
