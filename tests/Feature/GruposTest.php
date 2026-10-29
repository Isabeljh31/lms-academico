<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Compartido\Grupos\Index as GruposIndex;
use App\Livewire\Compartido\Tareas\Detalle;
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

        $componente->call('mover', [$estudiante->id], $grupo->id)->assertHasNoErrors();

        $this->assertTrue($grupo->fresh()->estudiantes->contains($estudiante));
    }

    public function test_cancelar_renombrado_descarta_el_nombre_y_los_errores_sin_modificar_el_grupo(): void
    {
        [$profesor, $curso] = $this->cursoConProfesor();
        $grupo = $curso->grupos()->create(['nombre' => 'Grupo original']);

        Livewire::actingAs($profesor)->test(GruposIndex::class, ['curso' => $curso])
            ->call('empezarRenombrar', $grupo->id)
            ->assertSee('wire:click="cancelarRenombrar"', false)
            ->set('nombreRenombrar', '')
            ->call('renombrar')->assertHasErrors('nombreRenombrar')
            ->call('cancelarRenombrar')
            ->assertSet('grupoRenombrarId', null)
            ->assertSet('nombreRenombrar', '')
            ->assertHasNoErrors('nombreRenombrar')
            ->assertSee('Grupo original');

        $this->assertSame('Grupo original', $grupo->fresh()->nombre);
    }

    public function test_cada_estudiante_aparece_solo_en_su_zona_al_mover_y_deshacer(): void
    {
        [$profesor, $curso] = $this->cursoConProfesor();
        $estudiante = $this->inscribir($curso);
        $sinGrupo = $this->inscribir($curso);
        $grupo = $curso->grupos()->create(['nombre' => 'Grupo 1']);
        $grupo->estudiantes()->attach($estudiante);

        $componente = Livewire::actingAs($profesor)->test(GruposIndex::class, ['curso' => $curso])
            ->assertViewHas('sinGrupo', fn ($alumnos) => $alumnos->pluck('id')->all() === [$sinGrupo->id]);
        $this->assertSame(1, substr_count($componente->html(), 'data-estudiante="'.$estudiante->id.'"'));

        $componente->call('mover', [$sinGrupo->id], $grupo->id)
            ->assertViewHas('sinGrupo', fn ($alumnos) => $alumnos->isEmpty());
        $this->assertSame(1, substr_count($componente->html(), 'data-estudiante="'.$sinGrupo->id.'"'));

        $componente->call('deshacerUltimo')
            ->assertViewHas('sinGrupo', fn ($alumnos) => $alumnos->pluck('id')->all() === [$sinGrupo->id]);
        $this->assertSame(1, substr_count($componente->html(), 'data-estudiante="'.$sinGrupo->id.'"'));
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
            ->call('mover', [$estudiante->id], $grupoB->id);

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

    public function test_mover_varios_a_la_vez_y_devolverlos_a_sin_grupo(): void
    {
        [$profesor, $curso] = $this->cursoConProfesor();
        [$ana, $beto] = [$this->inscribir($curso), $this->inscribir($curso)];
        $grupo = $curso->grupos()->create(['nombre' => 'Grupo 1']);

        $componente = Livewire::actingAs($profesor)->test(GruposIndex::class, ['curso' => $curso])
            ->call('mover', [$ana->id, $beto->id], $grupo->id)
            ->assertSee('2 estudiantes ahora están en Grupo 1.');
        $this->assertEqualsCanonicalizing([$ana->id, $beto->id], $grupo->estudiantes()->pluck('users.id')->all());

        $componente->call('mover', [$ana->id], null)->assertSee("{$ana->name} volvió a Sin grupo.");
        $this->assertSame([$beto->id], $grupo->estudiantes()->pluck('users.id')->all());
    }

    public function test_no_se_puede_superar_el_maximo_de_integrantes(): void
    {
        [$profesor, $curso] = $this->cursoConProfesor();
        $estudiantes = collect(range(1, 3))->map(fn () => $this->inscribir($curso));
        $grupo = $curso->grupos()->create(['nombre' => 'Grupo 1']);
        $grupo->estudiantes()->attach($estudiantes[0]);

        $componente = Livewire::actingAs($profesor)->test(GruposIndex::class, ['curso' => $curso])
            ->set('tamanoMaximo', '2')->call('guardarTamanoMaximo')->assertHasNoErrors()
            ->call('mover', [$estudiantes[1]->id, $estudiantes[2]->id], $grupo->id)
            ->assertSee('Grupo 1 admite 2 integrantes y solo le queda espacio para 1.');

        $this->assertSame(2, $curso->fresh()->tamano_maximo_grupo);
        $this->assertSame(1, $grupo->estudiantes()->count());

        $componente->call('mover', [$estudiantes[1]->id], $grupo->id)->assertSee('ahora está en Grupo 1.');
        $this->assertSame(2, $grupo->estudiantes()->count());

        $componente->set('tamanoMaximo', '1')->call('guardarTamanoMaximo')->assertHasErrors('tamanoMaximo')
            ->set('tamanoGrupoAuto', 3)->call('generarAutomatico')->assertHasErrors('tamanoGrupoAuto');
    }

    public function test_deshacer_devuelve_a_los_estudiantes_a_su_grupo_anterior(): void
    {
        [$profesor, $curso] = $this->cursoConProfesor();
        $estudiante = $this->inscribir($curso);
        $grupoA = $curso->grupos()->create(['nombre' => 'Grupo A']);
        $grupoB = $curso->grupos()->create(['nombre' => 'Grupo B']);
        $grupoA->estudiantes()->attach($estudiante);

        Livewire::actingAs($profesor)->test(GruposIndex::class, ['curso' => $curso])
            ->call('mover', [$estudiante->id], $grupoB->id)->assertSee('Deshacer')
            ->call('deshacerUltimo')->assertSee('Cambio deshecho.');

        $this->assertTrue($grupoA->estudiantes()->whereKey($estudiante->id)->exists());
        $this->assertFalse($grupoB->estudiantes()->whereKey($estudiante->id)->exists());
    }

    public function test_eliminar_un_grupo_devuelve_sus_integrantes_y_se_puede_deshacer(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $estudiante = $this->inscribir($curso);
        $grupo = $curso->grupos()->create(['nombre' => 'Grupo 1']);
        $grupo->estudiantes()->attach($estudiante);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);
        $entrega = Entrega::create(['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id, 'grupo_id' => $grupo->id, 'estado' => 'entregado']);

        $componente = Livewire::actingAs($profesor)->test(GruposIndex::class, ['curso' => $curso])
            ->call('eliminarGrupo', $grupo->id)
            ->assertSee('Grupo «Grupo 1» eliminado. Su integrante volvió a Sin grupo.');
        $this->assertDatabaseMissing('grupos', ['id' => $grupo->id]);
        $this->assertNull($entrega->fresh()->grupo_id);

        $componente->call('deshacerUltimo');

        $recuperado = $curso->grupos()->where('nombre', 'Grupo 1')->sole();
        $this->assertTrue($recuperado->estudiantes()->whereKey($estudiante->id)->exists());
        $this->assertSame($recuperado->id, $entrega->fresh()->grupo_id);
    }

    public function test_no_se_mueve_a_un_grupo_de_otro_curso_ni_puede_hacerlo_el_supervisor(): void
    {
        [$profesor, $curso] = $this->cursoConProfesor();
        [, $otroCurso] = $this->cursoConProfesor();
        $estudiante = $this->inscribir($curso);
        $ajeno = $otroCurso->grupos()->create(['nombre' => 'Ajeno']);
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($profesor)->test(GruposIndex::class, ['curso' => $curso])
            ->call('mover', [$estudiante->id], $ajeno->id)->assertNotFound();
        Livewire::actingAs($supervisor)->test(GruposIndex::class, ['curso' => $curso])
            ->call('mover', [$estudiante->id], null)->assertForbidden();

        $this->assertSame(0, $ajeno->estudiantes()->count());
    }
}
