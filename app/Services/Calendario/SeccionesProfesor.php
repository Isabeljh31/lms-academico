<?php

namespace App\Services\Calendario;

use App\Models\Curso;
use Illuminate\Support\Collection;

/**
 * Secciones de los cursos que dicta un profesor (Calendario del Profesor).
 *
 * Una sección es un registro de `cursos`: las secciones de una misma materia
 * comparten el nombre (Curso::normalizarNombre, el mismo criterio con el que
 * el Supervisor crea "misma materia, nueva sección") y se distinguen por su
 * código. El sufijo del código no se interpreta (el código puede escribirse a
 * mano o quedar vacío): se muestra el código completo y, si no hay, "Sección N"
 * según el orden.
 *
 * Es el único punto que decide el nombre visible, la sección, la agrupación y
 * el orden, para que las tarjetas, los filtros, la cuadrícula y el bloque
 * semanal digan lo mismo. Trabaja con los cursos ya cargados (sin consultas).
 */
class SeccionesProfesor
{
    /**
     * Desde cuántos cursos cada actividad de la cuadrícula lleva su curso en
     * texto (código o nombre). Con 1 o 2 cursos se ve como siempre.
     */
    public const CURSOS_CON_REFERENCIA = 3;

    /**
     * Más secciones que colores en la paleta: los colores se repiten y la
     * pantalla pasa a modo "muchos cursos" (buscador, atajos, grupos
     * colapsados y como máximo 3 actividades por día en la semana).
     */
    public const MUCHOS_CURSOS = 9;

    /** @var Collection<int, Curso> curso_id => curso, en el orden de visualización */
    protected Collection $ordenados;

    /** @var array<int, string> curso_id => clave de la materia */
    protected array $claves = [];

    /** @var array<string, list<int>> clave => ids de sus secciones, en orden */
    protected array $grupos = [];

    /**
     * @param  Collection<int, Curso>  $cursos
     */
    public function __construct(Collection $cursos)
    {
        $this->ordenados = $cursos
            ->sortBy([
                fn (Curso $a, Curso $b) => strcmp(Curso::normalizarNombre($a->nombre), Curso::normalizarNombre($b->nombre)),
                fn (Curso $a, Curso $b) => strcmp((string) $a->codigo, (string) $b->codigo),
                fn (Curso $a, Curso $b) => $a->id <=> $b->id,
            ])
            ->keyBy('id');

        foreach ($this->ordenados as $curso) {
            $clave = Curso::normalizarNombre($curso->nombre);
            $this->claves[$curso->id] = $clave;
            $this->grupos[$clave][] = $curso->id;
        }
    }

    /** ¿Hay otra sección con el mismo nombre entre los cursos del profesor? */
    public function tieneVariasSecciones(int $cursoId): bool
    {
        return count($this->grupos[$this->claves[$cursoId] ?? ''] ?? []) > 1;
    }

    /** Cantidad de secciones (cursos) del profesor. */
    public function total(): int
    {
        return $this->ordenados->count();
    }

    /** Modo "muchos cursos": más secciones que colores distintos (MUCHOS_CURSOS). */
    public function hayMuchos(): bool
    {
        return $this->total() >= self::MUCHOS_CURSOS;
    }

    /**
     * Curso en texto para una actividad de la cuadrícula, para no depender
     * solo del color: el código (o el nombre visible si no tiene) cuando hay
     * 3 o más cursos, o cuando hay otra sección con el mismo nombre. Null si
     * no hace falta (1 o 2 cursos distintos).
     */
    public function referenciaEnActividad(int $cursoId): ?string
    {
        return $this->total() >= self::CURSOS_CON_REFERENCIA || $this->tieneVariasSecciones($cursoId)
            ? $this->referenciaCorta($cursoId)
            : null;
    }

    /** Texto para buscar el curso por nombre o código, sin tildes ni mayúsculas. */
    public function textoBusqueda(int $cursoId): string
    {
        $curso = $this->ordenados->get($cursoId);

        return Curso::normalizarNombre(trim($this->nombreDeMateria($cursoId).' '.$curso?->nombre.' '.$curso?->codigo.' '.$this->codigo($cursoId)));
    }

    /** Hay al menos una materia con más de una sección. */
    public function haySecciones(): bool
    {
        return collect($this->grupos)->contains(fn (array $ids) => count($ids) > 1);
    }

    /** Código de la sección, o "Sección N" (orden dentro de su materia) si no tiene. */
    public function codigo(int $cursoId): string
    {
        $curso = $this->ordenados->get($cursoId);
        $posicion = array_search($cursoId, $this->grupos[$this->claves[$cursoId] ?? ''] ?? [], true);

        return $curso?->codigo ?: 'Sección '.(($posicion === false ? 0 : $posicion) + 1);
    }

    /** Lo que distingue a la sección, solo cuando hay más de una con el mismo nombre; si no, null. */
    public function seccion(int $cursoId): ?string
    {
        return $this->tieneVariasSecciones($cursoId) ? $this->codigo($cursoId) : null;
    }

    /**
     * "Matemática 1" o, si hay varias secciones, "Matemática 1 · MAT1-26-02".
     * El nombre es el de la materia (su primera sección), aunque otra sección
     * lo tenga escrito con otras mayúsculas o tildes.
     */
    public function nombreVisible(int $cursoId): string
    {
        $nombre = $this->nombreDeMateria($cursoId);
        $seccion = $this->seccion($cursoId);

        return $seccion ? "{$nombre} · {$seccion}" : $nombre;
    }

    /** Referencia corta (listas compactas): el código, o el nombre visible si el curso no tiene código. */
    public function referenciaCorta(int $cursoId): string
    {
        return $this->ordenados->get($cursoId)?->codigo ?: $this->nombreVisible($cursoId);
    }

    /**
     * Materias en orden, cada una con sus secciones en orden.
     *
     * @return Collection<int, array{clave: string, nombre: string, secciones: Collection<int, Curso>}>
     */
    public function grupos(): Collection
    {
        return collect($this->grupos)->map(fn (array $ids, string $clave) => [
            'clave' => $clave,
            'nombre' => $this->nombreDeMateria($ids[0]),
            'secciones' => collect($ids)->map(fn (int $id) => $this->ordenados->get($id)),
        ])->values();
    }

    /** Nombre de la materia a la que pertenece la sección (el de su primera sección en el orden). */
    public function nombreDeMateria(int $cursoId): string
    {
        $primera = $this->grupos[$this->claves[$cursoId] ?? ''][0] ?? $cursoId;

        return (string) $this->ordenados->get($primera)?->nombre;
    }

    /** Clave de la materia de un curso (para agrupar en el navegador). */
    public function clave(int $cursoId): string
    {
        return $this->claves[$cursoId] ?? '';
    }

    /**
     * Posición de cada curso en el orden de visualización.
     *
     * @return array<int, int> curso_id => posición
     */
    public function posiciones(): array
    {
        return $this->ordenados->keys()->flip()->all();
    }
}
