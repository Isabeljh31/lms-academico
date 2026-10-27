<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Debates\Index as DebatesIndex;
use App\Livewire\Debates\Show as DebatesShow;
use App\Models\Curso;
use App\Models\Debate;
use App\Models\DebateComentario;
use App\Notifications\NuevoDebatePublicado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Debates del curso (extensión fuera del ERS): gestión del Profesor,
 * participación de estudiantes matriculados, lectura del Supervisor y
 * protecciones de permisos, IDOR, longitudes y escape de contenido.
 */
class DebatesTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_el_profesor_crea_un_debate_y_al_publicarlo_se_avisa_a_los_estudiantes_una_sola_vez(): void
    {
        Notification::fake();
        $escenario = $this->escenario();

        $componente = Livewire::actingAs($escenario['profesor'])->test(DebatesIndex::class, ['curso' => $escenario['curso']])
            ->call('abrirNuevo')
            ->set('titulo', '¿Energía nuclear?')
            ->set('consigna', 'Argumenta a favor o en contra.')
            ->call('guardar')
            ->assertHasNoErrors();

        $debate = Debate::sole();
        $this->assertSame(['borrador', $escenario['profesor']->id], [$debate->estado, $debate->autor_id]);
        Notification::assertNothingSent();

        $componente->call('editar', $debate->id)->set('estado', 'publicado')->call('guardar')->assertHasNoErrors();
        Notification::assertSentTo($escenario['estudiante'], NuevoDebatePublicado::class);

        $componente->call('editar', $debate->id)->set('estado', 'borrador')->call('guardar');
        $componente->call('editar', $debate->id)->set('estado', 'publicado')->call('guardar');
        Notification::assertSentToTimes($escenario['estudiante'], NuevoDebatePublicado::class, 1);
    }

    public function test_el_profesor_cierra_reabre_y_elimina_un_debate(): void
    {
        $escenario = $this->escenario();
        $debate = Debate::factory()->create(['curso_id' => $escenario['curso']->id]);
        $componente = Livewire::actingAs($escenario['profesor'])->test(DebatesIndex::class, ['curso' => $escenario['curso']]);

        $componente->call('alternarCierre', $debate->id);
        $this->assertTrue($debate->fresh()->estaCerrado());
        $componente->call('alternarCierre', $debate->id);
        $this->assertFalse($debate->fresh()->estaCerrado());

        DebateComentario::factory()->create(['debate_id' => $debate->id, 'user_id' => $escenario['estudiante']->id]);
        $componente->call('eliminar', $debate->id);
        $this->assertSame([0, 0], [Debate::count(), DebateComentario::count()]);
    }

    public function test_valida_longitudes_de_titulo_y_comentario(): void
    {
        $escenario = $this->escenario();
        $debate = Debate::factory()->create(['curso_id' => $escenario['curso']->id]);

        Livewire::actingAs($escenario['profesor'])->test(DebatesIndex::class, ['curso' => $escenario['curso']])
            ->call('abrirNuevo')->set('titulo', str_repeat('a', 151))->set('consigna', 'Consigna')
            ->call('guardar')->assertHasErrors(['titulo' => 'max']);

        Livewire::actingAs($escenario['estudiante'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debate])
            ->set('nuevoComentario', str_repeat('a', 2001))->call('comentar')->assertHasErrors(['nuevoComentario' => 'max'])
            ->set('nuevoComentario', '')->call('comentar')->assertHasErrors(['nuevoComentario' => 'required']);

        $this->assertSame(0, DebateComentario::count());
    }

    public function test_el_estudiante_matriculado_comenta_responde_y_solo_edita_o_elimina_lo_suyo(): void
    {
        $escenario = $this->escenario();
        $debate = Debate::factory()->create(['curso_id' => $escenario['curso']->id]);
        $companero = $this->usuarioConRol(Rol::Estudiante->value);
        $this->matricular($companero, $escenario['curso']);
        $ajeno = DebateComentario::factory()->create(['debate_id' => $debate->id, 'user_id' => $companero->id]);

        $componente = Livewire::actingAs($escenario['estudiante'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debate])
            ->set('nuevoComentario', 'Estoy a favor.')->call('comentar')->assertHasNoErrors()
            ->call('responder', $ajeno->id)->set('respuesta', 'Buen punto.')->call('enviarRespuesta')->assertHasNoErrors();

        $propio = DebateComentario::where('contenido', 'Estoy a favor.')->sole();
        $respuesta = DebateComentario::where('contenido', 'Buen punto.')->sole();
        $this->assertSame($ajeno->id, $respuesta->parent_id);

        // Un solo nivel: no se responde a una respuesta.
        $this->assertNoEncontrado(fn () => $componente->call('responder', $respuesta->id));

        $componente->call('editar', $propio->id)->set('edicion', 'Estoy a favor, con matices.')->call('guardarEdicion')->assertHasNoErrors();
        $this->assertSame('Estoy a favor, con matices.', $propio->fresh()->contenido);
        $this->assertNotNull($propio->fresh()->editado_en);

        Livewire::actingAs($escenario['estudiante'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debate])
            ->call('editar', $ajeno->id)->assertForbidden();
        Livewire::actingAs($escenario['estudiante'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debate])
            ->call('eliminar', $ajeno->id)->assertForbidden();
        $this->assertNotNull($ajeno->fresh());

        Livewire::actingAs($escenario['estudiante'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debate])
            ->call('eliminar', $propio->id);
        $this->assertNull($propio->fresh());
    }

    public function test_el_comentario_del_profesor_lleva_la_etiqueta_docente_y_puede_moderar(): void
    {
        $escenario = $this->escenario();
        // Nombre fijo y sin tildes: en pruebas de Livewire assertSeeInOrder compara contra la respuesta
        // JSON, donde las tildes van escapadas (í), y un nombre al azar con tilde no se encontraba.
        $escenario['profesor']->update(['name' => 'Profesor Prueba']);
        $debate = Debate::factory()->create(['curso_id' => $escenario['curso']->id]);
        $delEstudiante = DebateComentario::factory()->create(['debate_id' => $debate->id, 'user_id' => $escenario['estudiante']->id]);

        $componente = Livewire::actingAs($escenario['profesor'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debate])
            ->set('nuevoComentario', 'Recuerden citar fuentes.')->call('comentar')->assertHasNoErrors()
            ->assertSeeInOrder(['Profesor Prueba', 'Docente', 'Recuerden citar fuentes.']);

        $componente->call('eliminar', $delEstudiante->id);
        $this->assertNull($delEstudiante->fresh());
    }

    public function test_un_debate_cerrado_es_de_solo_lectura(): void
    {
        $escenario = $this->escenario();
        $debate = Debate::factory()->cerrado()->create(['curso_id' => $escenario['curso']->id]);
        $propio = DebateComentario::factory()->create(['debate_id' => $debate->id, 'user_id' => $escenario['estudiante']->id]);

        $componente = Livewire::actingAs($escenario['estudiante'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debate])
            ->assertSee('Este debate está cerrado')
            ->assertDontSee('Publicar comentario');

        $componente->set('nuevoComentario', 'Tarde')->call('comentar')->assertForbidden();
        Livewire::actingAs($escenario['estudiante'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debate])
            ->call('editar', $propio->id)->assertForbidden();
        $this->assertSame(1, DebateComentario::count());
    }

    public function test_el_estudiante_solo_ve_debates_publicados_y_no_gestiona(): void
    {
        $escenario = $this->escenario();
        Debate::factory()->create(['curso_id' => $escenario['curso']->id, 'titulo' => 'Debate abierto']);
        $borrador = Debate::factory()->borrador()->create(['curso_id' => $escenario['curso']->id, 'titulo' => 'Debate en preparación']);

        Livewire::actingAs($escenario['estudiante'])->test(DebatesIndex::class, ['curso' => $escenario['curso']])
            ->assertSee('Debate abierto')
            ->assertDontSee('Debate en preparación')
            ->assertDontSee('Nuevo debate')
            ->call('abrirNuevo')->assertForbidden();

        $this->actingAs($escenario['estudiante'])->get(route('cursos.debates.show', [$escenario['curso'], $borrador]))->assertForbidden();
    }

    public function test_el_supervisor_solo_lee(): void
    {
        $escenario = $this->escenario();
        $debate = Debate::factory()->create(['curso_id' => $escenario['curso']->id, 'titulo' => 'Debate abierto']);

        $this->actingAs($escenario['supervisor'])->get(route('cursos.debates.index', $escenario['curso']))->assertOk()->assertSee('Debate abierto');
        $this->actingAs($escenario['supervisor'])->get(route('cursos.debates.show', [$escenario['curso'], $debate]))->assertOk()->assertDontSee('Publicar comentario');

        Livewire::actingAs($escenario['supervisor'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debate])
            ->set('nuevoComentario', 'Hola')->call('comentar')->assertForbidden();
        Livewire::actingAs($escenario['supervisor'])->test(DebatesIndex::class, ['curso' => $escenario['curso']])
            ->call('abrirNuevo')->assertForbidden();
    }

    public function test_sin_relacion_con_el_curso_no_hay_acceso(): void
    {
        $escenario = $this->escenario();
        $debate = Debate::factory()->create(['curso_id' => $escenario['curso']->id]);
        $otroProfesor = $this->usuarioConRol(Rol::Profesor->value);
        $noMatriculado = $this->usuarioConRol(Rol::Estudiante->value);

        foreach ([$otroProfesor, $noMatriculado] as $usuario) {
            $this->actingAs($usuario)->get(route('cursos.debates.index', $escenario['curso']))->assertForbidden();
            $this->actingAs($usuario)->get(route('cursos.debates.show', [$escenario['curso'], $debate]))->assertForbidden();
        }
    }

    public function test_proteccion_idor_entre_cursos(): void
    {
        $escenario = $this->escenario();
        $otroCurso = Curso::factory()->create(['profesor_id' => $escenario['profesor']->id]);
        $debateAjeno = Debate::factory()->create(['curso_id' => $otroCurso->id]);
        $debatePropio = Debate::factory()->create(['curso_id' => $escenario['curso']->id]);
        $comentarioAjeno = DebateComentario::factory()->create(['debate_id' => $debateAjeno->id, 'user_id' => $escenario['profesor']->id]);

        // Un debate de otro curso no se abre con la URL de este curso.
        $this->actingAs($escenario['estudiante'])->get(route('cursos.debates.show', [$escenario['curso'], $debateAjeno]))->assertNotFound();

        // Las acciones solo encuentran debates y comentarios de su propio curso o debate.
        $this->assertNoEncontrado(fn () => Livewire::actingAs($escenario['profesor'])->test(DebatesIndex::class, ['curso' => $escenario['curso']])
            ->call('eliminar', $debateAjeno->id));
        $this->assertNoEncontrado(fn () => Livewire::actingAs($escenario['profesor'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debatePropio])
            ->call('eliminar', $comentarioAjeno->id));

        $this->assertNotNull($debateAjeno->fresh());
        $this->assertNotNull($comentarioAjeno->fresh());
    }

    public function test_el_contenido_se_muestra_escapado(): void
    {
        $escenario = $this->escenario();
        $debate = Debate::factory()->create(['curso_id' => $escenario['curso']->id, 'consigna' => '<b>consigna</b>']);
        DebateComentario::factory()->create(['debate_id' => $debate->id, 'user_id' => $escenario['estudiante']->id, 'contenido' => '<script>alert("x")</script>']);

        $this->actingAs($escenario['estudiante'])->get(route('cursos.debates.show', [$escenario['curso'], $debate]))
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<b>consigna</b>', false);
    }

    public function test_los_comentarios_se_paginan(): void
    {
        $escenario = $this->escenario();
        $debate = Debate::factory()->create(['curso_id' => $escenario['curso']->id]);
        foreach (range(1, DebatesShow::POR_PAGINA + 1) as $numero) {
            DebateComentario::factory()->create([
                'debate_id' => $debate->id, 'user_id' => $escenario['estudiante']->id,
                'contenido' => "Comentario número {$numero}.", 'created_at' => now()->addMinutes($numero),
            ]);
        }

        Livewire::actingAs($escenario['estudiante'])->test(DebatesShow::class, ['curso' => $escenario['curso'], 'debate' => $debate])
            ->assertSee('Comentario número 1.')
            ->assertDontSee('Comentario número 11.');
    }

    /** Las acciones Livewire con un id ajeno no encuentran el registro (404). */
    private function assertNoEncontrado(callable $accion): void
    {
        try {
            $accion();
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Se esperaba que el registro no se encontrara (404).');
    }
}
