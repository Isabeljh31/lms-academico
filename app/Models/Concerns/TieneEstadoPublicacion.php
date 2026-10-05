<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * ERS RF-37 y RF-38 (regla de negocio 9): Módulos, Tareas y Quizzes tienen
 * estado Borrador, Publicado o Programado (con fecha y hora de publicación
 * automática). El contenido en Borrador, o Programado con fecha futura,
 * nunca es visible para el estudiante.
 *
 * Tareas y Quizzes además conservan la bandera `visible` (ocultar sin
 * borrar, ver Tarea::puedeEliminarse) y dependen de que su Módulo sea visible.
 *
 * Columnas: estado_contenido, publicar_en.
 */
trait TieneEstadoPublicacion
{
    /** @var array<string, string> */
    public static array $estadosPublicacion = [
        'borrador' => 'Borrador',
        'publicado' => 'Publicado',
        'programado' => 'Programado',
    ];

    public function esVisibleParaEstudiante(): bool
    {
        $estado = $this->estado_contenido ?? 'publicado';

        $publicado = $estado === 'publicado'
            || ($estado === 'programado' && $this->publicar_en !== null && $this->publicar_en->lte(now()));

        if (! $publicado) {
            return false;
        }

        if ($this->dependeDeVisibilidadYModulo()) {
            return (bool) $this->getAttribute('visible') && ($this->modulo?->esVisibleParaEstudiante() ?? true);
        }

        return true;
    }

    public function scopeVisiblesParaEstudiante(Builder $query): Builder
    {
        $query->where(fn (Builder $q) => $q
            ->where($q->qualifyColumn('estado_contenido'), 'publicado')
            ->orWhere(fn (Builder $q) => $q
                ->where($q->qualifyColumn('estado_contenido'), 'programado')
                ->where($q->qualifyColumn('publicar_en'), '<=', now())
            )
        );

        if ($this->dependeDeVisibilidadYModulo()) {
            $query->where($query->qualifyColumn('visible'), true)
                ->whereHas('modulo', fn (Builder $modulo) => $modulo->visiblesParaEstudiante());
        }

        return $query;
    }

    public function etiquetaEstadoPublicacion(): string
    {
        if ($this->dependeDeVisibilidadYModulo() && ! $this->getAttribute('visible')) {
            return 'Oculto';
        }

        if (($this->estado_contenido ?? 'publicado') === 'programado') {
            return $this->publicar_en?->isFuture()
                ? 'Programado para '.$this->publicar_en->formatoFechaHora()
                : 'Publicado';
        }

        return self::$estadosPublicacion[$this->estado_contenido ?? 'publicado'] ?? 'Publicado';
    }

    protected function dependeDeVisibilidadYModulo(): bool
    {
        return false;
    }
}
