<?php

namespace App\Policies;

use App\Models\Curso;
use App\Models\User;

/**
 * Permisos sobre Cursos según la matriz de la sección 5 del ERS.
 * "Propio" = cursos donde el profesor es titular o profesor adicional (RF-23).
 *
 * - ver: Supervisor (todos), Profesor (propios) y Estudiante matriculado en
 *   un curso visible (publicado o archivado). No hay catálogo público.
 * - crear / administrar / matricular: solo Supervisor (crear y clonar cursos,
 *   esquema de evaluación, matrícula).
 * - gestionar: módulos, lecciones, recursos, tareas, quizzes, grupos y
 *   asistencia. Solo el Profesor del curso: la matriz no le da estas acciones
 *   al Supervisor.
 * - supervisar: consulta de solo lectura de esas pantallas (tareas, quizzes,
 *   libro de calificaciones, asistencia, grupos). Supervisor (todos) y
 *   Profesor (propios), igual que "Ver reportes de su(s) curso(s)".
 * - calificar: registrar y editar notas. Solo el Profesor del curso.
 * - darDeBaja: Supervisor (todos) y Profesor (propios).
 */
class CursoPolicy
{
    public function ver(User $usuario, Curso $curso): bool
    {
        if ($usuario->hasRole('supervisor')) {
            return true;
        }

        if ($usuario->hasRole('profesor')) {
            return $curso->esDocente($usuario);
        }

        return $usuario->hasRole('estudiante')
            && $curso->esVisibleParaEstudiantes()
            && $usuario->estaMatriculadoEn($curso);
    }

    public function crear(User $usuario): bool
    {
        return $usuario->hasRole('supervisor');
    }

    public function administrar(User $usuario, Curso $curso): bool
    {
        return $usuario->hasRole('supervisor');
    }

    public function matricular(User $usuario, Curso $curso): bool
    {
        return $usuario->hasRole('supervisor');
    }

    public function darDeBaja(User $usuario, Curso $curso): bool
    {
        return $usuario->hasRole('supervisor') || $this->gestionar($usuario, $curso);
    }

    public function gestionar(User $usuario, Curso $curso): bool
    {
        return $usuario->hasRole('profesor') && $curso->esDocente($usuario);
    }

    public function supervisar(User $usuario, Curso $curso): bool
    {
        return $usuario->hasRole('supervisor') || $this->gestionar($usuario, $curso);
    }

    public function calificar(User $usuario, Curso $curso): bool
    {
        return $this->gestionar($usuario, $curso);
    }

    /**
     * ERS 2.4 y RF-26: no hay autoinscripción; solo el Supervisor matricula
     * o el sistema externo por API.
     */
    public function inscribirse(User $usuario, Curso $curso): bool
    {
        return false;
    }

    /**
     * Progreso/boleta de notas (RF-59): quien supervisa el curso o el propio
     * estudiante con matrícula activa.
     */
    public function verProgreso(User $usuario, Curso $curso): bool
    {
        return $this->supervisar($usuario, $curso)
            || ($usuario->hasRole('estudiante') && $usuario->estaMatriculadoEn($curso));
    }
}
