<?php

namespace App\Services\Calendario;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cursos que ve cada usuario en su calendario y el color propio de cada uno.
 *
 * Es la única fuente del color por curso: el Calendario y la bandeja de
 * propuestas del asistente lo toman de aquí, así un curso se reconoce con el
 * mismo color en ambas pantallas.
 */
class ColoresCursos
{
    /** Colores de la paleta con buen contraste para texto blanco, uno por curso. */
    public const PALETA = ['#2541B2', '#B2604A', '#3C7A75', '#5B4E8A', '#86715E', '#1768AC', '#3F6B4A', '#03256C'];

    /** Color para un curso que ya no está en la lista del usuario (archivado o dado de baja). */
    public const NEUTRO = '#94A3B8';

    /**
     * Cursos cuyas actividades aparecen en el calendario del usuario, según su rol.
     *
     * @return Builder<Curso>
     */
    public function consultaCursosDe(User $usuario): Builder
    {
        return match (true) {
            $usuario->hasRole('supervisor') => Curso::where('estado', 'publicado'),
            $usuario->hasRole('profesor') => Curso::dictadosPor($usuario)->where('estado', '!=', 'archivado'),
            // Los cursos archivados (periodos anteriores) se consultan desde "Mis cursos", no en el calendario.
            default => Curso::whereHas('inscripcionesActivas', fn ($q) => $q->where('estudiante_id', $usuario->id))
                ->visiblesParaEstudiantes()->where('estado', '!=', 'archivado'),
        };
    }

    /**
     * Color de cada curso según su posición en la lista ordenada por nombre.
     *
     * @param  Collection<int, Curso>  $cursos  ya ordenados por nombre
     * @return array<int, string> curso_id => color
     */
    public function asignar(Collection $cursos): array
    {
        return $cursos->values()->mapWithKeys(fn (Curso $curso, int $indice) => [
            $curso->id => self::PALETA[$indice % count(self::PALETA)],
        ])->all();
    }

    /**
     * @return array<int, string> curso_id => color
     */
    public function paraUsuario(User $usuario): array
    {
        return $this->asignar($this->consultaCursosDe($usuario)->orderBy('nombre')->orderBy('id')->get(['id', 'nombre']));
    }
}
