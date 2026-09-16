<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Tareas\Detalle;
use App\Livewire\Tareas\Formulario;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TareasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    protected function cursoConProfesor(): array
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);

        return [$profesor, $curso, $modulo];
    }

    public function test_profesor_puede_crear_una_tarea_en_su_curso(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();

        Livewire::actingAs($profesor)
            ->test(Formulario::class, ['curso' => $curso])
            ->set('titulo', 'Tarea 1')
            ->set('modulo_id', $modulo->id)
            ->set('fecha_entrega', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('peso_nota', 5)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tareas', ['curso_id' => $curso->id, 'titulo' => 'Tarea 1']);
    }

    public function test_estudiante_no_puede_crear_tareas(): void
    {
        [, $curso] = $this->cursoConProfesor();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($estudiante)
            ->get(route('cursos.tareas.crear', $curso))
            ->assertForbidden();
    }

    public function test_estudiante_inscrito_puede_entregar_una_tarea(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);

        Livewire::actingAs($estudiante)
            ->test(Detalle::class, ['tarea' => $tarea])
            ->set('archivo', UploadedFile::fake()->create('tarea.pdf', 100))
            ->call('entregar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entregas', [
            'tarea_id' => $tarea->id,
            'estudiante_id' => $estudiante->id,
            'estado' => 'entregado',
        ]);
    }

    public function test_estudiante_no_inscrito_no_puede_ver_ni_entregar(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);

        // No inscrito: la política "ver" ya lo bloquea antes de llegar al formulario de entrega.
        $this->actingAs($estudiante)
            ->get(route('cursos.tareas.show', [$curso, $tarea]))
            ->assertForbidden();
    }

    public function test_profesor_puede_calificar_una_entrega(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);
        $entrega = Entrega::factory()->create(['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id]);

        Livewire::actingAs($profesor)
            ->test(Detalle::class, ['tarea' => $tarea])
            ->set("notas.{$entrega->id}", '18')
            ->set("feedbacks.{$entrega->id}", 'Excelente trabajo')
            ->call('calificar', $entrega->id)
            ->assertHasNoErrors();

        $entrega->refresh();
        $this->assertEquals('calificado', $entrega->estado);
        $this->assertEquals(18, $entrega->nota);
    }

    public function test_otro_profesor_no_puede_ver_la_tarea_de_un_curso_ajeno(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $otroProfesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);
        Entrega::factory()->create(['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id]);

        // Un profesor que no es dueño del curso ni administrador no puede ni ver la tarea,
        // así que mucho menos calificar sus entregas.
        $this->actingAs($otroProfesor)
            ->get(route('cursos.tareas.show', [$curso, $tarea]))
            ->assertForbidden();
    }
}
