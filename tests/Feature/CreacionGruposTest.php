<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Compartido\Grupos\Index as GruposIndex;
use App\Models\Curso;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

class CreacionGruposTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_no_se_puede_crear_un_grupo_sin_integrantes(): void
    {
        $this->crearRoles();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        Livewire::actingAs($profesor)
            ->test(GruposIndex::class, ['curso' => $curso])
            ->set('nuevoGrupoNombre', 'Grupo sin integrantes')
            ->call('crearGrupo');

        $this->assertDatabaseMissing('grupos', [
            'curso_id' => $curso->id,
            'nombre' => 'Grupo sin integrantes',
        ]);
    }

    public function test_el_docente_crea_un_grupo_con_estudiantes_matriculados(): void
    {
        $this->crearRoles();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $estudianteUno = $this->usuarioConRol(Rol::Estudiante->value);
        $estudianteDos = $this->usuarioConRol(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        $this->matricular($estudianteUno, $curso);
        $this->matricular($estudianteDos, $curso);

        Livewire::actingAs($profesor)
            ->test(GruposIndex::class, ['curso' => $curso])
            ->set('nuevoGrupoNombre', 'Grupo A')
            ->set('integrantesIniciales', [$estudianteUno->id, $estudianteDos->id])
            ->call('crearGrupo')
            ->assertHasNoErrors();

        $grupo = Grupo::where('curso_id', $curso->id)
            ->where('nombre', 'Grupo A')
            ->first();

        $this->assertNotNull($grupo);
        $this->assertCount(2, $grupo->estudiantes);
        $this->assertTrue($grupo->estudiantes->contains($estudianteUno));
        $this->assertTrue($grupo->estudiantes->contains($estudianteDos));
    }

    public function test_el_formulario_muestra_estudiantes_disponibles_para_crear_un_grupo(): void
    {  
        $this->crearRoles();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $estudiante = $this->usuarioConRol(Rol::Estudiante->value, [
            'name' => 'Ana Estudiante',
        ]);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        $this->matricular($estudiante, $curso);

        Livewire::actingAs($profesor)
         ->test(GruposIndex::class, ['curso' => $curso])
         ->assertSee('Integrantes disponibles')
         ->assertSee('Ana Estudiante')
         ->set('nuevoGrupoNombre', 'Grupo de Ana')
         ->set('integrantesIniciales', [$estudiante->id])
            ->call('crearGrupo')
            ->assertHasNoErrors()
            ->assertSee('Grupo de Ana');

        $this->assertDatabaseHas('grupos', [
            'curso_id' => $curso->id,
            'nombre' => 'Grupo de Ana',
        ]);
    }
    
    public function test_no_se_puede_crear_un_grupo_con_un_estudiante_de_otro_curso(): void
    {
        $this->crearRoles();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $estudianteAjeno = $this->usuarioConRol(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        Livewire::actingAs($profesor)
            ->test(GruposIndex::class, ['curso' => $curso])
            ->set('nuevoGrupoNombre', 'Grupo inválido')
            ->set('integrantesIniciales', [$estudianteAjeno->id])
            ->call('crearGrupo')
            ->assertHasErrors('integrantesIniciales');

        $this->assertDatabaseMissing('grupos', [
            'curso_id' => $curso->id,
            'nombre' => 'Grupo inválido',
        ]);
    }

    public function test_no_se_puede_crear_un_grupo_con_un_estudiante_que_ya_tiene_grupo(): void
    {
        $this->crearRoles();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $estudiante = $this->usuarioConRol(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        $this->matricular($estudiante, $curso);

        $grupoExistente = $curso->grupos()->create([
            'nombre' => 'Grupo existente',
        ]);

        $grupoExistente->estudiantes()->attach($estudiante->id);

        Livewire::actingAs($profesor)
            ->test(GruposIndex::class, ['curso' => $curso])
            ->set('nuevoGrupoNombre', 'Grupo duplicado')
            ->set('integrantesIniciales', [$estudiante->id])
            ->call('crearGrupo')
            ->assertHasErrors('integrantesIniciales');

        $this->assertDatabaseMissing('grupos', [
            'curso_id' => $curso->id,
            'nombre' => 'Grupo duplicado',
        ]);

        $this->assertTrue(
            $grupoExistente->estudiantes()->whereKey($estudiante->id)->exists()
        );
    }

    public function test_no_se_puede_crear_un_grupo_que_supere_el_maximo(): void
    {
        $this->crearRoles();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $estudianteUno = $this->usuarioConRol(Rol::Estudiante->value);
        $estudianteDos = $this->usuarioConRol(Rol::Estudiante->value);
        $estudianteTres = $this->usuarioConRol(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
            'tamano_maximo_grupo' => 2,
        ]);

        foreach ([$estudianteUno, $estudianteDos, $estudianteTres] as $estudiante) {
            $this->matricular($estudiante, $curso);
        }

        Livewire::actingAs($profesor)
            ->test(GruposIndex::class, ['curso' => $curso])
            ->set('nuevoGrupoNombre', 'Grupo demasiado grande')
            ->set('integrantesIniciales', [
                $estudianteUno->id,
                $estudianteDos->id,
                $estudianteTres->id,
            ])
            ->call('crearGrupo')
            ->assertHasErrors('integrantesIniciales');

        $this->assertDatabaseMissing('grupos', [
            'curso_id' => $curso->id,
            'nombre' => 'Grupo demasiado grande',
        ]);
    }

    public function test_los_contadores_solo_incluyen_matriculas_activas(): void
    {
        $this->crearRoles();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $conGrupo = $this->usuarioConRol(Rol::Estudiante->value);
        $sinGrupo = $this->usuarioConRol(Rol::Estudiante->value);
        $dadoDeBaja = $this->usuarioConRol(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        $this->matricular($conGrupo, $curso);
        $this->matricular($sinGrupo, $curso);
        $this->matricular($dadoDeBaja, $curso, [
            'estado' => 'inactivo',
            'activo_local' => false,
        ]);

        $grupo = $curso->grupos()->create([
            'nombre' => 'Grupo A',
        ]);

        // El historial puede conservar a un estudiante dado de baja en el grupo.
        $grupo->estudiantes()->attach([$conGrupo->id, $dadoDeBaja->id]);

        Livewire::actingAs($profesor)
            ->test(GruposIndex::class, ['curso' => $curso])
            ->assertViewHas('totalMatriculados', 2)
            ->assertViewHas('totalEnGrupos', 1)
            ->assertViewHas(
                'sinGrupo',
                fn ($estudiantes) => $estudiantes->count() === 1
                    && $estudiantes->first()->id === $sinGrupo->id
            );
    }

    public function test_el_estudiante_consulta_su_grupo_en_el_curso(): void
    {
        $this->crearRoles();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $estudiante = $this->usuarioConRol(Rol::Estudiante->value);
        $companero = $this->usuarioConRol(Rol::Estudiante->value);
        $estudianteAjeno = $this->usuarioConRol(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        foreach ([$estudiante, $companero, $estudianteAjeno] as $alumno) {
            $this->matricular($alumno, $curso);
        }

        $miGrupo = $curso->grupos()->create(['nombre' => 'Mi grupo A']);
        $otroGrupo = $curso->grupos()->create(['nombre' => 'Otro grupo B']);

        $miGrupo->estudiantes()->attach([$estudiante->id, $companero->id]);
        $otroGrupo->estudiantes()->attach($estudianteAjeno->id);

        $componente = Livewire::actingAs($estudiante)
            ->test(\App\Livewire\Compartido\Cursos\Show::class, ['curso' => $curso])
            ->assertSee('Mi grupo')
            ->assertSee('Mi grupo A');

        $grupoConsultado = $componente->instance()->miGrupoDelCurso;

        $this->assertNotNull($grupoConsultado);
        $this->assertSame($miGrupo->id, $grupoConsultado->id);
        $this->assertTrue($grupoConsultado->estudiantes->contains($companero));
        $this->assertFalse($grupoConsultado->estudiantes->contains($estudianteAjeno));
    }

    public function test_el_estudiante_sin_grupo_ve_un_mensaje_informativo(): void
    {
        $this->crearRoles();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $estudiante = $this->usuarioConRol(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        $this->matricular($estudiante, $curso);

        Livewire::actingAs($estudiante)
            ->test(\App\Livewire\Compartido\Cursos\Show::class, ['curso' => $curso])
            ->assertSee('Todavía no tienes un grupo asignado en este curso.');
    }

    public function test_un_estudiante_dado_de_baja_no_accede_al_curso(): void
    {
        $this->crearRoles();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $estudiante = $this->usuarioConRol(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        $this->matricular($estudiante, $curso, [
            'estado' => 'inactivo',
            'activo_local' => false,
        ]);

        $grupo = $curso->grupos()->create(['nombre' => 'Grupo anterior']);
        $grupo->estudiantes()->attach($estudiante->id);

        $this->actingAs($estudiante)
            ->get(route('cursos.show', $curso))
            ->assertForbidden();
    }

    public function test_un_grupo_con_entregas_no_se_elimina_sin_motivo(): void
    {
        $escenario = $this->escenario();

        $grupo = $escenario['curso']->grupos()->create([
            'nombre' => 'Grupo con historial',
        ]);

        $grupo->estudiantes()->attach($escenario['estudiante']->id);

        $entrega = \App\Models\Entrega::factory()->create([
            'tarea_id' => $escenario['tarea']->id,
            'estudiante_id' => $escenario['estudiante']->id,
            'grupo_id' => $grupo->id,
            'estado' => 'entregado',
        ]);

        Livewire::actingAs($escenario['profesor'])
            ->test(GruposIndex::class, ['curso' => $escenario['curso']])
            ->call('eliminarGrupo', $grupo->id);

        $this->assertDatabaseHas('grupos', [
            'id' => $grupo->id,
            'curso_id' => $escenario['curso']->id,
        ]);

        $this->assertDatabaseHas('entregas', [
            'id' => $entrega->id,
            'grupo_id' => $grupo->id,
        ]);
    }

    public function test_eliminar_un_grupo_con_entregas_registra_el_motivo_y_conserva_la_entrega(): void
    {
        $escenario = $this->escenario();

        $grupo = $escenario['curso']->grupos()->create([
            'nombre' => 'Grupo con historial',
        ]);

        $grupo->estudiantes()->attach($escenario['estudiante']->id);

        $entrega = \App\Models\Entrega::factory()->create([
            'tarea_id' => $escenario['tarea']->id,
            'estudiante_id' => $escenario['estudiante']->id,
            'grupo_id' => $grupo->id,
            'estado' => 'entregado',
        ]);

        Livewire::actingAs($escenario['profesor'])
            ->test(GruposIndex::class, ['curso' => $escenario['curso']])
            ->set('motivoEliminacion', 'Reorganización de equipos')
            ->call('eliminarGrupo', $grupo->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('grupos', [
            'id' => $grupo->id,
        ]);

        $this->assertDatabaseHas('entregas', [
            'id' => $entrega->id,
        ]);

        $this->assertDatabaseHas('bitacoras_auditoria', [
            'ejecutado_por' => $escenario['profesor']->id,
            'curso_id' => $escenario['curso']->id,
            'tipo_evento' => 'eliminacion_grupo',
            'entidad' => 'grupo',
            'entidad_id' => $grupo->id,
            'motivo' => 'Reorganización de equipos',
        ]);
    }
}