<?php

namespace App\Models;

use Database\Factories\DebateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Debate de un curso (extensión fuera del ERS, ver docs/TRAZABILIDAD.md).
 * El Profesor publica una consigna; los docentes del curso y los
 * estudiantes matriculados comentan y responden (un nivel). Un debate
 * cerrado queda en solo lectura.
 */
class Debate extends Model
{
    /** @use HasFactory<DebateFactory> */
    use HasFactory;

    /**
     * Subconjunto de los estados de publicación del contenido (RF-37): un
     * debate no se programa, solo se publica o queda en borrador.
     *
     * @var array<string, string>
     */
    public const ESTADOS = [
        'borrador' => 'Borrador (no visible)',
        'publicado' => 'Publicado',
    ];

    public const MAX_TITULO = 150;

    public const MAX_COMENTARIO = 2000;

    protected $fillable = ['curso_id', 'autor_id', 'titulo', 'consigna', 'estado', 'publicado_en', 'cerrado_en'];

    protected function casts(): array
    {
        return [
            'publicado_en' => 'datetime',
            'cerrado_en' => 'datetime',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(DebateComentario::class);
    }

    /** Comentarios principales (no respuestas). */
    public function comentariosPrincipales(): HasMany
    {
        return $this->comentarios()->whereNull('parent_id');
    }

    public function estaPublicado(): bool
    {
        return $this->estado === 'publicado';
    }

    public function estaCerrado(): bool
    {
        return $this->cerrado_en !== null;
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('estado', 'publicado');
    }
}
