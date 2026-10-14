<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Admin\Comunicados\Index as ComunicadosIndex;
use App\Livewire\Admin\Profesores\Index as ProfesoresIndex;
use App\Livewire\Cursos\Formulario as CursoFormulario;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminProfesoresTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $profesor;

    protected User $estudiante;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }

        $this->admin = User::factory()->create(['name' => 'Admin Test'])->assignRole(Rol::Supervisor->value);
        $this->profesor = User::factory()->create(['name' => 'Profesor Test'])->assignRole(Rol::Profesor->value);
        $this->estudiante = User::factory()->create(['name' => 'Estudiante Test'])->assignRole(Rol::Estudiante->value);
    }

    public function test_administrador_puede_acceder_al_directorio_de_profesores(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.profesores.index'))
            ->assertOk()
            ->assertSee('Profesores y carga académica')
            ->assertSee($this->profesor->name);
    }

    public function test_profesor_y_estudiante_no_pueden_acceder_a_admin_profesores(): void
    {
        $this->actingAs($this->profesor)
            ->get(route('admin.profesores.index'))
            ->assertForbidden();

        $this->actingAs($this->estudiante)
            ->get(route('admin.profesores.index'))
            ->assertForbidden();
    }

    public function test_administrador_puede_crear_un_nuevo_profesor_con_rol(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->set('nuevoNombres', 'María Elena')
            ->set('nuevoApellidos', 'Quispe Rojas')
            ->set('nuevoDni', '41239876')
            ->set('nuevoEmail', 'maria.elena@lms.edu.pe')
            ->call('crearProfesor')
            ->assertHasNoErrors();

        $nuevo = User::where('email', 'maria.elena@lms.edu.pe')->first();
        $this->assertNotNull($nuevo);
        $this->assertSame('María Elena Quispe Rojas', $nuevo->name);
        $this->assertSame('41239876', $nuevo->dni);
        $this->assertTrue($nuevo->hasRole(Rol::Profesor->value));
        // ERS RF-03/RF-05: contraseña temporal generada y cambio obligatorio.
        $this->assertTrue($nuevo->primer_login);
    }

    public function test_administrador_puede_reasignar_un_curso_a_un_profesor(): void
    {
        $curso = Curso::factory()->create(['profesor_id' => $this->admin->id, 'nombre' => 'Química Orgánica']);

        Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->call('abrirModalAsignar', $this->profesor->id)
            ->set('cursoAAsignarId', $curso->id)
            ->call('asignarCurso')
            ->assertHasNoErrors();

        $this->assertEquals($this->profesor->id, $curso->fresh()->profesor_id);
    }

    public function test_administrador_puede_desasignar_un_curso(): void
    {
        $curso = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'nombre' => 'Física General']);

        Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->call('desasignarCurso', $curso->id);

        $this->assertEquals($this->admin->id, $curso->fresh()->profesor_id);
    }

    public function test_al_desasignar_al_titular_el_profesor_adicional_pasa_a_titular(): void
    {
        $curso = Curso::factory()->create(['profesor_id' => $this->profesor->id]);
        $coDocente = User::factory()->create();
        $coDocente->assignRole(Rol::Profesor->value);
        $curso->profesoresAdicionales()->attach($coDocente);

        Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->call('desasignarCurso', $curso->id);

        $this->assertEquals($coDocente->id, $curso->fresh()->profesor_id);
        $this->assertCount(0, $curso->profesoresAdicionales()->get());
    }

    public function test_administrador_puede_publicar_un_comunicado_institucional(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ComunicadosIndex::class)
            ->set('titulo', 'Reunión general de claustro docente')
            ->set('contenido', 'Se convoca a todos los docentes este viernes a las 10:00 AM.')
            ->set('prioridad', 'urgente')
            ->set('destinatario', 'profesores')
            ->call('crearComunicado')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comunicados', [
            'titulo' => 'Reunión general de claustro docente',
            'prioridad' => 'urgente',
            'destinatario' => 'profesores',
            'activo' => true,
        ]);
    }

    public function test_profesor_y_estudiante_no_pueden_gestionar_comunicados(): void
    {
        $this->actingAs($this->profesor)
            ->get(route('admin.comunicados.index'))
            ->assertForbidden();

        $this->actingAs($this->estudiante)
            ->get(route('admin.comunicados.index'))
            ->assertForbidden();
    }

    public function test_administrador_puede_asignar_profesor_desde_formulario_de_curso(): void
    {
        $profesor2 = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($this->admin)
            ->test(CursoFormulario::class)
            ->set('nombre', 'Biología Celular')
            ->set('descripcion', 'Curso de bio experimental')
            ->set('profesor_id', $profesor2->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $curso = Curso::where('nombre', 'Biología Celular')->firstOrFail();
        $this->assertEquals($profesor2->id, $curso->profesor_id);
    }

    public function test_comunicados_activos_se_filtran_segun_rol(): void
    {
        Comunicado::create([
            'user_id' => $this->admin->id,
            'titulo' => 'Solo para profesores',
            'contenido' => 'Aviso docente',
            'destinatario' => 'profesores',
            'prioridad' => 'informativo',
            'activo' => true,
        ]);

        Comunicado::create([
            'user_id' => $this->admin->id,
            'titulo' => 'Solo para estudiantes',
            'contenido' => 'Aviso alumnos',
            'destinatario' => 'estudiantes',
            'prioridad' => 'informativo',
            'activo' => true,
        ]);

        $comunicadosProf = Comunicado::activos()->paraRol('profesor')->pluck('titulo');
        $this->assertTrue($comunicadosProf->contains('Solo para profesores'));
        $this->assertFalse($comunicadosProf->contains('Solo para estudiantes'));

        $comunicadosEst = Comunicado::activos()->paraRol('estudiante')->pluck('titulo');
        $this->assertFalse($comunicadosEst->contains('Solo para profesores'));
        $this->assertTrue($comunicadosEst->contains('Solo para estudiantes'));
    }
}
