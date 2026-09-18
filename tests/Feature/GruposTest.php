<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Grupos\Index as GruposIndex;
use App\Livewire\Tareas\Detalle;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Grupo;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GruposTest extends TestCase
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

    protected function inscribir(Curso $curso): User
    {
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        return $estudiante;
    }

    public function test_profesor_puede_crear_un_grupo_y_asignar_estudiantes(): void
    {
        [$profesor, $curso] = $this->cursoConProfesor();
        $estudiante = $this->inscribir($curso);

        $componente = Livewire::actingAs($profesor)
            ->test(GruposIndex::class, ['curso' => $curso])
            ->set('nuevoGrupoNombre', 'Grupo A')
            ->call('crearGrupo')
            ->assertHasNoErrors();

        $grupo = Grupo::where('curso_id', $curso->id)->where('nombre', 'Grupo A')->firstOrFail();

        $componente->set("seleccion.$grupo->id", $estudiante->id)
            ->call('asignar', $grupo->id);

        $this->assertTrue($grupo->fresh()->estudiantes->contains($estudiante));
    }

    public function test_asignar_a_otro_grupo_del_mismo_curso_lo_quita_del_anterior(): void
    {
        [$profesor, $curso] = $this->cursoConProfesor();
        $estudiante = $this->inscribir($curso);
        $grupoA = $curso->grupos()->create(['nombre' => 'Grupo A']);
        $grupoB = $curso->grupos()->create(['nombre' => 'Grupo B']);
        $grupoA->estudiantes()->attach($estudiante);

        Livewire::actingAs($profesor)
            ->test(GruposIndex::class, ['curso' => $curso])
            ->set("seleccion.$grupoB->id", $estudiante->id)
            ->call('asignar', $grupoB->id);

        $this->assertFalse($grupoA->fresh()->estudiantes->contains($estudiante));
        $this->assertTrue($grupoB->fresh()->estudiantes->contains($estudiante));
    }

    public function test_estudiante_no_puede_gestionar_grupos_de_un_curso(): void
    {
        [, $curso] = $this->cursoConProfesor();
        $estudiante = $this->inscribir($curso);

        $this->actingAs($estudiante)
            ->get(route('cursos.grupos.index', $curso))
            ->assertForbidden();
    }

    public function test_generar_grupos_automaticos_reparte_solo_a_los_sin_grupo(): void
    {
        [$profesor, $curso] = $this->cursoConProfesor();
        $estudiantes = collect(range(1, 4))->map(fn () => $this->inscribir($curso));

        Livewire::actingAs($profesor)
            ->test(GruposIndex::class, ['curso' => $curso])
            ->set('tamanoGrupoAuto', 2)
            ->call('generarAutomatico')
            ->assertHasNoErrors();

        $this->assertEquals(2, Grupo::where('curso_id', $curso->id)->count());
        $estudiantesAgrupados = Grupo::where('curso_id', $curso->id)->with('estudiantes')->get()
            ->flatMap(fn ($g) => $g->estudiantes->pluck('id'));
        $this->assertEqualsCanonicalizing($estudiantes->pluck('id')->all(), $estudiantesAgrupados->all());
    }

    public function test_al_entregar_una_tarea_grupal_se_crea_una_entrega_para_cada_integrante(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'tipo_entrega' => 'grupal']);
        $miembro1 = $this->inscribir($curso);
        $miembro2 = $this->inscribir($curso);
        $grupo = $curso->grupos()->create(['nombre' => 'Grupo A']);
        $grupo->estudiantes()->attach([$miembro1->id, $miembro2->id]);

        Livewire::actingAs($miembro1)
            ->test(Detalle::class, ['tarea' => $tarea])
            ->set('archivo', UploadedFile::fake()->create('trabajo.pdf', 100))
            ->call('entregar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entregas', ['tarea_id' => $tarea->id, 'estudiante_id' => $miembro1->id, 'grupo_id' => $grupo->id]);
        $this->assertDatabaseHas('entregas', ['tarea_id' => $tarea->id, 'estudiante_id' => $miembro2->id, 'grupo_id' => $grupo->id]);
    }

    public function test_estudiante_sin_grupo_no_puede_entregar_una_tarea_grupal(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'tipo_entrega' => 'grupal']);
        $estudiante = $this->inscribir($curso);

        Livewire::actingAs($estudiante)
            ->test(Detalle::class, ['tarea' => $tarea])
            ->set('archivo', UploadedFile::fake()->create('trabajo.pdf', 100))
            ->call('entregar')
            ->assertHasErrors('archivo');

        $this->assertDatabaseCount('entregas', 0);
    }

    public function test_calificar_una_entrega_grupal_propaga_la_nota_a_todo_el_grupo(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'tipo_entrega' => 'grupal']);
        $miembro1 = $this->inscribir($curso);
        $miembro2 = $this->inscribir($curso);
        $grupo = $curso->grupos()->create(['nombre' => 'Grupo A']);
        $grupo->estudiantes()->attach([$miembro1->id, $miembro2->id]);

        $entrega1 = Entrega::factory()->create(['tarea_id' => $tarea->id, 'estudiante_id' => $miembro1->id, 'grupo_id' => $grupo->id]);
        $entrega2 = Entrega::factory()->create(['tarea_id' => $tarea->id, 'estudiante_id' => $miembro2->id, 'grupo_id' => $grupo->id]);

        Livewire::actingAs($profesor)
            ->test(Detalle::class, ['tarea' => $tarea])
            ->set("notas.{$entrega1->id}", '16')
            ->set("feedbacks.{$entrega1->id}", 'Buen trabajo en equipo')
            ->call('calificar', $entrega1->id)
            ->assertHasNoErrors();

        $this->assertEquals(16, $entrega1->fresh()->nota);
        $this->assertEquals(16, $entrega2->fresh()->nota);
        $this->assertEquals('Buen trabajo en equipo', $entrega2->fresh()->feedback);
        $this->assertEquals('calificado', $entrega2->fresh()->estado);
    }
}
