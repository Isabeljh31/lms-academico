<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ERS RF-35: Lección como sub-entidad dentro del Módulo.
 * RF-36: Puede tener video_url y material_url.
 * RF-37: Estado borrador | publicado | programado.
 */
class Leccion extends Model
{
    protected $table = 'lecciones';

    protected $fillable = [
        'modulo_id', 'titulo', 'descripcion', 'video_url',
        'material_url', 'estado', 'fecha_publicacion', 'orden',
    ];

    protected $casts = [
        'fecha_publicacion' => 'datetime',
        'orden'             => 'integer',
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
        if ($this->estado === 'publicado') return true;
        if ($this->estado === 'programado' && $this->fecha_publicacion?->isPast()) return true;
        return false;
    }

    public function scopePublicadas($query)
    {
        return $query->where('estado', 'publicado')
            ->orWhere(fn ($q) =>
                $q->where('estado', 'programado')
                  ->where('fecha_publicacion', '<=', now())
            );
    }
}
