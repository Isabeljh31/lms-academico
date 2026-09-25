<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Curso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Livewire\Auth\CambiarPasswordPrimerLogin;
use App\Livewire\Mensajeria\Bandeja;
use App\Livewire\Asistencia\Index as AsistenciaIndex;
use App\Livewire\Cursos\Show as CursosShow;

class RolesYFuncionalidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::create(['name' => 'supervisor']);
        \Spatie\Permission\Models\Role::create(['name' => 'profesor']);
        \Spatie\Permission\Models\Role::create(['name' => 'estudiante']);
    }

    public function test_flujo_primer_login()
    {
        $user = User::factory()->create(['primer_login' => true, 'password' => 'password']);
        $user->assignRole('estudiante');

        // Middleware lo redirige a primer-login
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('primer-login'));

        // Probar el componente
        Livewire::actingAs($user)
            ->test(CambiarPasswordPrimerLogin::class)
            ->set('password', 'NuevaPass123')
            ->set('password_confirmation', 'NuevaPass123')
            ->call('guardar')
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->primer_login);
    }

    public function test_supervisor_clonar_curso_y_metricas()
    {
        $admin = User::factory()->create(['primer_login' => false]);
        $admin->assignRole('supervisor');

        $curso = Curso::create(['nombre' => 'Matematica', 'codigo' => 'MAT1', 'descripcion' => 'A', 'profesor_id' => $admin->id]);

        // Puede ver metricas
        $this->actingAs($admin)->get('/admin/metricas')->assertStatus(200);

        // Puede ver panel de curso
        $this->actingAs($admin)->get(route('cursos.show', $curso))->assertStatus(200);
        
        // Simular componente de mostrar curso
        Livewire::actingAs($admin)
            ->test(CursosShow::class, ['curso' => $curso])
            ->set('mostrarModalClonar', true)
            ->set('clonNombre', 'Matematica Clon')
            ->set('clonCodigo', 'MAT2')
            ->call('clonarCurso');

        $this->assertDatabaseHas('cursos', ['codigo' => 'MAT2']);
    }

    public function test_profesor_asistencia_y_mensajeria()
    {
        $profesor = User::factory()->create(['primer_login' => false]);
        $profesor->assignRole('profesor');

        $estudiante = User::factory()->create(['primer_login' => false]);
        $estudiante->assignRole('estudiante');

        $curso = Curso::create(['nombre' => 'Lenguaje', 'codigo' => 'LEN1', 'descripcion' => 'B', 'profesor_id' => $profesor->id]);
        $profesor->cursosComoProfesor()->attach($curso->id);
        $estudiante->inscripciones()->create(['curso_id' => $curso->id, 'activo_local' => true]);

        // Probar Asistencia
        $this->actingAs($profesor)->get(route('asistencia.index', $curso))->assertStatus(200);

        Livewire::actingAs($profesor)
            ->test(AsistenciaIndex::class, ['curso' => $curso])
            ->call('marcarTodos', 'presente')
            ->call('guardarAsistencia');

        $this->assertDatabaseHas('registros_asistencia', [
            'curso_id' => $curso->id,
            'estudiante_id' => $estudiante->id,
            'estado' => 'presente'
        ]);

        // Probar Mensajería (Profesor envia mensaje a Estudiante)
        Livewire::actingAs($profesor)
            ->test(Bandeja::class)
            ->set('vista', 'nuevo')
            ->set('cursoContextoId', $curso->id)
            ->set('destinatarioId', $estudiante->id)
            ->set('asunto', 'Bienvenido al curso')
            ->set('cuerpo', 'Hola')
            ->call('enviar');

        $this->assertDatabaseHas('mensajes', [
            'remitente_id' => $profesor->id,
            'destinatario_id' => $estudiante->id,
            'asunto' => 'Bienvenido al curso'
        ]);
    }
}
