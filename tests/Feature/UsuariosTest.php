<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Usuarios\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Livewire\Volt\Volt;
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
        $admin = User::factory()->create()->assignRole(Rol::Administrador->value);

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
        $admin = User::factory()->create()->assignRole(Rol::Administrador->value);
        $usuario = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('actualizarRol', $usuario->id, Rol::Profesor->value);

        $this->assertTrue($usuario->fresh()->hasRole(Rol::Profesor->value));
        $this->assertFalse($usuario->fresh()->hasRole(Rol::Estudiante->value));
    }

    public function test_administrador_no_puede_cambiarse_el_rol_a_si_mismo_via_policy(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Administrador->value);

        $this->assertFalse(Gate::forUser($admin)->allows('actualizarRol', $admin));
    }

    public function test_registro_publico_asigna_rol_estudiante_por_defecto(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Nuevo Estudiante')
            ->set('email', 'nuevo@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $usuario = User::where('email', 'nuevo@example.com')->firstOrFail();

        $this->assertTrue($usuario->hasRole(Rol::Estudiante->value));
    }
}
