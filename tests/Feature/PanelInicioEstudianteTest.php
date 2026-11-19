<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Estudiante\PanelInicio;
use App\Models\Curso;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rediseño visual del rol estudiante (dashboard principal). No verifica
 * estilos, solo que los datos y enlaces sean correctos y que el panel de
 * profesor/administrador siga exactamente igual que antes.
 */
class PanelInicioEstudianteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    public function test_estudiante_ve_el_panel_de_inicio_rediseñado(): void
    {
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($estudiante)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire('estudiante.panel-inicio');
    }

    public function test_profesor_y_administrador_siguen_viendo_el_panel_anterior_sin_cambios(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        $this->actingAs($profesor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeLivewire('estudiante.panel-inicio')
            ->assertSee('Hola,')
            ->assertSee('Rol:');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeLivewire('estudiante.panel-inicio')
            ->assertSee('Hola,');
    }

    public function test_no_estudiante_no_puede_montar_el_panel_de_inicio_directamente(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($profesor)
            ->test(PanelInicio::class)
            ->assertForbidden();
    }

    public function test_las_tarjetas_de_curso_muestran_progreso_y_enlazan_al_curso_correcto(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'nombre' => 'Curso Rediseño']);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        // Una tarea vencida sin entrega: debe aparecer en "próximas entregas"
        // marcada como atrasada, y el progreso del curso debe ser 0%.
        $tarea = Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'fecha_entrega' => now()->subDay(),
        ]);

        Livewire::actingAs($estudiante)
            ->test(PanelInicio::class)
            ->assertSee('Curso Rediseño')
            ->assertSee($tarea->titulo)
            ->assertSeeHtml(route('cursos.show', $curso))
            ->assertSeeHtml(route('cursos.tareas.show', [$curso, $tarea]));
    }

    public function test_el_progreso_general_es_100_cuando_todo_esta_completo(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);
        $tarea->entregas()->create([
            'estudiante_id' => $estudiante->id,
            'archivo_url' => 'https://example.test/archivo.pdf',
            'estado' => 'entregado',
        ]);

        Livewire::actingAs($estudiante)
            ->test(PanelInicio::class)
            ->assertSee('100%')
            ->assertSee('Sin entregas pendientes');
    }

    public function test_el_panel_no_muestra_tareas_asignadas_a_otro_grupo(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

     $curso = Curso::factory()->create([
        'profesor_id' => $profesor->id,
        ]);

        $modulo = Modulo::factory()->create([
        'curso_id' => $curso->id,
        ]);

        $curso->inscripciones()->create([
        'estudiante_id' => $estudiante->id,
        'fecha_inscripcion' => now(),
        'estado' => 'activo',
        'activo_local' => true,
        ]);

        $otroGrupo = \App\Models\Grupo::create([
        'curso_id' => $curso->id,
        'nombre' => 'Grupo de otros estudiantes',
        ]);

        Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'titulo' => 'Actividad general visible',
            'visible' => true,
            'fecha_entrega' => now()->addDays(2),
        ]);

        Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'titulo' => 'Actividad privada de otro grupo',
            'visible' => true,
            'tipo_entrega' => 'grupal',
            'grupo_asignado_id' => $otroGrupo->id,
            'fecha_entrega' => now()->addDays(2),
        ]);

        Livewire::actingAs($estudiante)
            ->test(PanelInicio::class)
            ->assertSee('Actividad general visible')
            ->assertDontSee('Actividad privada de otro grupo');
    }

    public function test_el_panel_muestra_tareas_del_grupo_y_oculta_las_programadas(): void
    {
        // Se fija la fecha para que ambas entregas caigan dentro del mes.
        $this->travelTo(now()->startOfMonth()->addDay()->startOfDay());

        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        $modulo = Modulo::factory()->create([
            'curso_id' => $curso->id,
        ]);

        $curso->inscripciones()->create([
            'estudiante_id' => $estudiante->id,
            'fecha_inscripcion' => now(),
            'estado' => 'activo',
            'activo_local' => true,
        ]);

        $grupo = \App\Models\Grupo::create([
            'curso_id' => $curso->id,
            'nombre' => 'Grupo del estudiante',
        ]);

        $grupo->estudiantes()->attach($estudiante->id);

        Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'titulo' => 'Actividad de mi grupo',
            'visible' => true,
            'tipo_entrega' => 'grupal',
            'grupo_asignado_id' => $grupo->id,
            'fecha_entrega' => now()->addDays(3),
        ]);

        Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'titulo' => 'Actividad programada privada',
            'visible' => true,
            'estado_contenido' => 'programado',
            'publicar_en' => now()->addDay(),
            'fecha_entrega' => now()->addDays(4),
        ]);

        Livewire::actingAs($estudiante)
            ->test(PanelInicio::class)
            ->assertSee('Actividad de mi grupo')
            ->assertDontSee('Actividad programada privada');
    }

    public function test_el_progreso_no_cuenta_tareas_de_otro_grupo(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
        ]);

        $modulo = Modulo::factory()->create([
            'curso_id' => $curso->id,
        ]);

        $curso->inscripciones()->create([
            'estudiante_id' => $estudiante->id,
            'fecha_inscripcion' => now(),
            'estado' => 'activo',
            'activo_local' => true,
        ]);

        $otroGrupo = \App\Models\Grupo::create([
            'curso_id' => $curso->id,
            'nombre' => 'Otro grupo',
        ]);

        $tareaGeneral = Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'titulo' => 'Tarea general completada',
            'visible' => true,
            'fecha_entrega' => now()->addDays(3),
        ]);

        $tareaGeneral->entregas()->create([
            'estudiante_id' => $estudiante->id,
            'archivo_url' => 'https://example.test/entrega.pdf',
            'estado' => 'entregado',
        ]);

        Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'titulo' => 'Tarea exclusiva de otro grupo',
            'visible' => true,
            'tipo_entrega' => 'grupal',
            'grupo_asignado_id' => $otroGrupo->id,
            'fecha_entrega' => now()->addDays(3),
        ]);

        $progreso = app(\App\Services\Reportes\ProgresoAcademicoService::class)
            ->avance($estudiante, $curso);

        $this->assertSame(100, $progreso);
    }
}
