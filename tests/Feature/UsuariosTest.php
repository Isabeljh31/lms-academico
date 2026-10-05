<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Usuarios\Index;
use App\Mail\BienvenidaMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    public function test_administrador_puede_ver_el_listado_de_usuarios(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        $this->actingAs($admin)
            ->get(route('admin.usuarios.index'))
            ->assertOk();
    }

    public function test_profesor_no_puede_acceder_al_listado_de_usuarios(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        $this->actingAs($profesor)
            ->get(route('admin.usuarios.index'))
            ->assertForbidden();
    }

    public function test_estudiante_no_puede_acceder_al_listado_de_usuarios(): void
    {
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($estudiante)
            ->get(route('admin.usuarios.index'))
            ->assertForbidden();
    }

    public function test_invitado_es_redirigido_a_login(): void
    {
        $this->get(route('admin.usuarios.index'))
            ->assertRedirect(route('login'));
    }

    public function test_administrador_puede_cambiar_el_rol_de_otro_usuario(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $usuario = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('actualizarRol', $usuario->id, Rol::Profesor->value);

        $this->assertTrue($usuario->fresh()->hasRole(Rol::Profesor->value));
        $this->assertFalse($usuario->fresh()->hasRole(Rol::Estudiante->value));
    }

    public function test_administrador_no_puede_cambiarse_el_rol_a_si_mismo_via_policy(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        $this->assertFalse(Gate::forUser($admin)->allows('actualizarRol', $admin));
    }

    /**
     * Reemplaza a la prueba de registro público (ERS 2.4 lo prohíbe): el
     * Supervisor crea la cuenta con rol estudiante por defecto, contraseña
     * temporal y cambio obligatorio en el primer ingreso (RF-03, RF-05).
     */
    public function test_supervisor_crea_cuenta_de_estudiante_con_password_temporal(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Nuevo')
            ->set('nuevoApellidos', 'Estudiante')
            ->set('nuevoEmail', 'nuevo@example.com')
            ->call('crearUsuario')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'nuevo@example.com')->firstOrFail();

        $this->assertSame('Nuevo Estudiante', $usuario->name);
        $this->assertTrue($usuario->hasRole(Rol::Estudiante->value));
        $this->assertTrue($usuario->primer_login);
        Mail::assertSent(BienvenidaMail::class, fn ($mail) => $mail->hasTo('nuevo@example.com'));
    }
}
