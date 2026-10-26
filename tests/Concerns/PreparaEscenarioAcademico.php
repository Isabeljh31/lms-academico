<?php

namespace Tests\Concerns;

use App\Enums\Rol;
use App\Models\Curso;
use App\Models\EsquemaEvaluacion;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\Tarea;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Datos comunes de las pruebas de Fase 3: roles del sistema y un curso con
 * profesor titular, un estudiante matriculado, un módulo, una tarea y un quiz.
 */
trait PreparaEscenarioAcademico
{
    protected function crearRoles(): void
    {
        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    protected function usuarioConRol(string $rol, array $atributos = []): User
    {
        return User::factory()->create($atributos)->assignRole($rol);
    }

    /**
     * @return array{supervisor: User, profesor: User, estudiante: User, curso: Curso, modulo: Modulo, tarea: Tarea, quiz: Quiz}
     */
    protected function escenario(): array
    {
        $this->crearRoles();

        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);
        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $estudiante = $this->usuarioConRol(Rol::Estudiante->value);

        // modalidad "virtual" para que editar este curso de prueba por el formulario
        // no exija aula/horario (solo obligatorios en presencial/remoto).
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'nombre' => 'Curso de prueba', 'modalidad' => 'virtual']);
        $this->matricular($estudiante, $curso);

        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'fecha_entrega' => now()->addWeek()]);
        $quiz = Quiz::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);

        return compact('supervisor', 'profesor', 'estudiante', 'curso', 'modulo', 'tarea', 'quiz');
    }

    protected function matricular(User $estudiante, Curso $curso, array $atributos = []): void
    {
        $curso->inscripciones()->create([
            'estudiante_id' => $estudiante->id,
            'fecha_inscripcion' => now(),
            'origen' => 'manual',
            'estado' => 'activo',
            'activo_local' => true,
            ...$atributos,
        ]);
    }

    /**
     * Esquema válido (100 %) con dos rubros de 40 % y 60 %.
     */
    protected function esquemaValido(Curso $curso): EsquemaEvaluacion
    {
        $esquema = EsquemaEvaluacion::firstOrCreate(['curso_id' => $curso->id]);
        $esquema->rubros()->create(['nombre' => 'Parcial', 'semana' => 4, 'peso' => 40, 'orden' => 1]);
        $esquema->rubros()->create(['nombre' => 'Final', 'semana' => 8, 'peso' => 60, 'orden' => 2]);

        return $esquema->load('rubros');
    }
}
