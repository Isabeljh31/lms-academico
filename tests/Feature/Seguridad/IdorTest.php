<?php

namespace Tests\Feature\Seguridad;

use App\Enums\Rol;
use App\Livewire\Asistencia\Index as AsistenciaIndex;
use App\Livewire\Cursos\EsquemaEvaluacion;
use App\Livewire\Cursos\GestionMatricula;
use App\Livewire\Cursos\LibroCalificaciones;
use App\Livewire\Docente\PorCalificar\Index as PorCalificar;
use App\Livewire\Lecciones\Index as LeccionesIndex;
use App\Livewire\Mensajeria\Bandeja;
use App\Livewire\Tareas\Detalle;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Mensaje;
use App\Models\Modulo;
use App\Models\NotaRubro;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Referencias directas inseguras (IDOR): ninguna acción acepta identificadores
 * de registros de otro curso u otro usuario.
 */
class IdorTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    /**
     * Otro curso con su propio profesor, estudiante, tarea y entrega.
     *
     * @return array{curso: Curso, profesor: User, estudiante: User, tarea: Tarea, entrega: Entrega, modulo: Modulo}
     */
    protected function cursoAjeno(): array
    {
        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $estudiante = $this->usuarioConRol(Rol::Estudiante->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $this->matricular($estudiante, $curso);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);
        $entrega = $tarea->entregas()->create(['estudiante_id' => $estudiante->id, 'estado' => 'entregado', 'respuesta_texto' => 'Mi respuesta']);

        return compact('curso', 'profesor', 'estudiante', 'tarea', 'entrega', 'modulo');
    }

    public function test_el_profesor_no_califica_entregas_de_otro_curso_desde_por_calificar(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->cursoAjeno();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($escenario['profesor'])->test(PorCalificar::class)
            ->call('abrirCalificar', $ajeno['entrega']->id);
    }

    public function test_el_profesor_no_califica_una_entrega_de_otra_tarea_desde_el_detalle(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->cursoAjeno();

        try {
            Livewire::actingAs($escenario['profesor'])
                ->test(Detalle::class, ['tarea' => $escenario['tarea']])
                ->set("notas.{$ajeno['entrega']->id}", '20')
                ->call('calificar', $ajeno['entrega']->id);
        } catch (ModelNotFoundException) {
            // Esperado: la entrega no pertenece a la tarea.
        }

        $this->assertNull($ajeno['entrega']->fresh()->nota);
    }

    public function test_el_libro_de_calificaciones_rechaza_rubros_de_otro_curso(): void
    {
        $escenario = $this->escenario();
        $this->esquemaValido($escenario['curso']);
        $ajeno = $this->cursoAjeno();
        $rubroAjeno = $this->esquemaValido($ajeno['curso'])->rubros->first();

        try {
            Livewire::actingAs($escenario['profesor'])
                ->test(LibroCalificaciones::class, ['curso' => $escenario['curso']])
                ->call('abrirModalNota', $rubroAjeno->id, $escenario['estudiante']->id);
        } catch (ModelNotFoundException) {
            // Esperado.
        }

        $this->assertSame(0, NotaRubro::count());
    }

    public function test_el_libro_de_calificaciones_rechaza_estudiantes_no_matriculados(): void
    {
        $escenario = $this->escenario();
        $rubro = $this->esquemaValido($escenario['curso'])->rubros->first();
        $ajeno = $this->cursoAjeno();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($escenario['profesor'])
            ->test(LibroCalificaciones::class, ['curso' => $escenario['curso']])
            ->call('abrirModalNota', $rubro->id, $ajeno['estudiante']->id);
    }

    public function test_la_matricula_no_da_de_baja_inscripciones_de_otro_curso(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->cursoAjeno();
        $inscripcionAjena = $ajeno['curso']->inscripciones()->first();

        try {
            Livewire::actingAs($escenario['supervisor'])
                ->test(GestionMatricula::class, ['curso' => $escenario['curso']])
                ->call('confirmarBaja', $inscripcionAjena->id);
        } catch (ModelNotFoundException) {
            // Esperado.
        }

        $this->assertTrue($inscripcionAjena->fresh()->estaActiva());
    }

    public function test_las_lecciones_de_otro_modulo_no_se_pueden_editar_ni_eliminar(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->cursoAjeno();
        $leccionAjena = $ajeno['modulo']->lecciones()->create(['titulo' => 'Ajena', 'estado' => 'publicado', 'orden' => 1]);

        foreach (['editar', 'eliminar'] as $accion) {
            try {
                Livewire::actingAs($escenario['profesor'])
                    ->test(LeccionesIndex::class, ['modulo' => $escenario['modulo']])
                    ->call($accion, $leccionAjena->id);
            } catch (ModelNotFoundException) {
                // Esperado.
            }
        }

        $this->assertNotNull($leccionAjena->fresh());
    }

    public function test_la_asistencia_no_usa_sesiones_de_otro_curso(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->cursoAjeno();
        $sesionAjena = $ajeno['curso']->sesionesClase()->create(['titulo' => 'Ajena', 'fecha' => now(), 'numero_sesion' => 1]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($escenario['profesor'])
            ->test(AsistenciaIndex::class, ['curso' => $escenario['curso']])
            ->call('abrirPaseLista', $sesionAjena->id);
    }

    public function test_el_esquema_no_se_modifica_desde_un_rubro_de_otro_curso(): void
    {
        $escenario = $this->escenario();
        $this->esquemaValido($escenario['curso']);
        $ajeno = $this->cursoAjeno();
        $rubroAjeno = $this->esquemaValido($ajeno['curso'])->rubros->first();

        try {
            Livewire::actingAs($escenario['supervisor'])
                ->test(EsquemaEvaluacion::class, ['curso' => $escenario['curso']])
                ->set('rubros', [
                    ['id' => $rubroAjeno->id, 'nombre' => 'Hackeado', 'semana' => 1, 'peso' => 100],
                ])
                ->call('guardar');
        } catch (ModelNotFoundException) {
            // Esperado.
        }

        $this->assertSame('Parcial', $rubroAjeno->fresh()->nombre);
    }

    public function test_un_estudiante_no_abre_mensajes_de_otras_personas(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->cursoAjeno();
        $mensaje = Mensaje::create([
            'remitente_id' => $ajeno['profesor']->id,
            'destinatario_id' => $ajeno['estudiante']->id,
            'asunto' => 'Privado',
            'cuerpo' => 'Contenido privado',
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($escenario['estudiante'])->test(Bandeja::class)->call('abrirMensaje', $mensaje->id);
    }

    public function test_un_estudiante_no_puede_escribir_a_profesores_de_otros_cursos(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->cursoAjeno();

        Livewire::actingAs($escenario['estudiante'])->test(Bandeja::class)
            ->set('cursoContextoId', $escenario['curso']->id)
            ->set('destinatarioId', $ajeno['profesor']->id)
            ->set('asunto', 'Hola')
            ->set('cuerpo', 'Mensaje')
            ->call('enviar')
            ->assertForbidden();

        $this->assertSame(0, Mensaje::count());
    }

    public function test_un_estudiante_no_ve_la_entrega_ni_la_nota_de_otro_estudiante(): void
    {
        $escenario = $this->escenario();
        $companero = $this->usuarioConRol(Rol::Estudiante->value, ['name' => 'Compañero Secreto']);
        $this->matricular($companero, $escenario['curso']);
        $escenario['tarea']->entregas()->create([
            'estudiante_id' => $companero->id, 'estado' => 'calificado', 'nota' => 19.5,
            'respuesta_texto' => 'Respuesta del compañero', 'feedback' => 'Comentario privado',
        ]);

        $this->actingAs($escenario['estudiante'])
            ->get(route('cursos.tareas.show', [$escenario['curso'], $escenario['tarea']]))
            ->assertOk()
            ->assertDontSee('Respuesta del compañero')
            ->assertDontSee('Comentario privado');
    }
}
