<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Quizzes\Index as QuizzesIndex;
use App\Livewire\Tareas\Index as TareasIndex;
use App\Models\Curso;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rediseño visual del rol estudiante en los listados de tareas y quizzes
 * (tarjetas con jerarquía por urgencia). No toca el listado del profesor.
 */
class ListadosEstudianteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    public function test_profesor_sigue_viendo_el_listado_de_tareas_con_conteo_de_entregas(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);

        Livewire::actingAs($profesor)
            ->test(TareasIndex::class, ['curso' => $curso])
            ->assertSee('entregas');
    }

    public function test_estudiante_ve_tarjetas_de_tareas_con_badge_de_estado(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'titulo' => 'Tarea atrasada',
            'fecha_entrega' => now()->subDay(),
        ]);
        $completa = Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'titulo' => 'Tarea completa',
            'fecha_entrega' => now()->addWeek(),
        ]);
        $completa->entregas()->create([
            'estudiante_id' => $estudiante->id,
            'archivo_url' => 'https://example.test/a.pdf',
            'estado' => 'entregado',
        ]);

        Livewire::actingAs($estudiante)
            ->test(TareasIndex::class, ['curso' => $curso])
            ->assertSee('Tarea atrasada')
            ->assertSee('Tarea completa')
            ->assertSee('Atrasado')
            ->assertSee('Entregado')
            ->assertDontSee('entregas'); // "N entregas" es solo de la vista de gestión.
    }

    public function test_estudiante_ve_tarjetas_de_quizzes_con_puntaje_o_pendiente(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        $quiz = Quiz::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'tema' => 'Quiz resuelto']);
        $quiz->resultados()->create(['estudiante_id' => $estudiante->id, 'respuestas' => [0], 'puntaje' => 17.5]);
        Quiz::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'tema' => 'Quiz pendiente']);

        Livewire::actingAs($estudiante)
            ->test(QuizzesIndex::class, ['curso' => $curso])
            ->assertSee('Quiz resuelto')
            ->assertSee('17.50 / 20')
            ->assertSee('Quiz pendiente')
            ->assertSee('Pendiente')
            ->assertDontSee('resueltos'); // "N resueltos" es solo de la vista de gestión.
    }
}
