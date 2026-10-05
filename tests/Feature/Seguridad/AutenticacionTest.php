<?php

namespace Tests\Feature\Seguridad;

use App\Enums\Rol;
use App\Livewire\Auth\CambiarPasswordPrimerLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * ERS RF-05, RF-06, RF-15, RNF-01 y RNF-05.
 */
class AutenticacionTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
    }

    protected function intentarLogin(string $email, string $password)
    {
        return Volt::test('pages.auth.login')
            ->set('form.email', $email)
            ->set('form.password', $password)
            ->call('login');
    }

    public function test_las_contrasenas_se_guardan_con_hash_bcrypt(): void
    {
        $usuario = User::factory()->create(['password' => 'ClaveSegura123']);

        $this->assertNotSame('ClaveSegura123', $usuario->getRawOriginal('password'));
        $this->assertTrue(Hash::check('ClaveSegura123', $usuario->getRawOriginal('password')));
        $this->assertStringStartsWith('$2y$', $usuario->getRawOriginal('password'));
    }

    public function test_bloquea_la_cuenta_tras_tres_intentos_fallidos(): void
    {
        $usuario = User::factory()->create();

        $this->intentarLogin($usuario->email, 'incorrecta');
        $this->intentarLogin($usuario->email, 'incorrecta');
        $this->intentarLogin($usuario->email, 'incorrecta')->assertHasErrors('form.email');

        $this->assertTrue($usuario->fresh()->estaBloqueada());

        // Aun con la contraseña correcta, la cuenta bloqueada no ingresa.
        $this->intentarLogin($usuario->email, 'password')->assertHasErrors('form.email');
        $this->assertGuest();
    }

    public function test_el_bloqueo_dura_quince_minutos_y_luego_permite_ingresar(): void
    {
        $usuario = User::factory()->create();

        foreach (range(1, 3) as $intento) {
            $this->intentarLogin($usuario->email, 'incorrecta');
        }

        $this->travel(16)->minutes();

        $this->intentarLogin($usuario->email, 'password')->assertHasNoErrors();
        $this->assertAuthenticatedAs($usuario);
        $this->assertSame(0, $usuario->fresh()->intentos_fallidos);
    }

    public function test_el_limite_de_intentos_es_configurable(): void
    {
        config(['auth.bloqueo_login.intentos' => 5]);
        $usuario = User::factory()->create();

        foreach (range(1, 4) as $intento) {
            $this->intentarLogin($usuario->email, 'incorrecta');
        }

        $this->assertFalse($usuario->fresh()->estaBloqueada());
    }

    public function test_registra_en_el_log_los_intentos_fallidos(): void
    {
        Log::spy();
        $usuario = User::factory()->create();

        $this->intentarLogin($usuario->email, 'incorrecta');

        Log::shouldHaveReceived('warning')->withArgs(fn ($mensaje, $contexto) => $mensaje === 'Inicio de sesión fallido' && $contexto['email'] === $usuario->email);
    }

    public function test_una_cuenta_desactivada_no_puede_ingresar(): void
    {
        $usuario = User::factory()->inactivo()->create();

        $this->intentarLogin($usuario->email, 'password')->assertHasErrors('form.email');
        $this->assertGuest();
    }

    public function test_el_aviso_de_cuenta_desactivada_no_aparece_con_contrasena_incorrecta(): void
    {
        $usuario = User::factory()->inactivo()->create();

        $this->intentarLogin($usuario->email, 'incorrecta')
            ->assertHasErrors('form.password')
            ->assertHasNoErrors('form.email');
    }

    public function test_una_sesion_abierta_se_cierra_si_la_cuenta_se_desactiva(): void
    {
        $usuario = $this->usuarioConRol(Rol::Estudiante->value);
        $this->actingAs($usuario);
        $usuario->update(['activo' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_el_primer_ingreso_obliga_a_cambiar_la_contrasena_antes_de_todo(): void
    {
        $usuario = $this->usuarioConRol(Rol::Estudiante->value, ['primer_login' => true]);

        foreach (['dashboard', 'cursos.index', 'profile', 'reportes.dashboard'] as $ruta) {
            $this->actingAs($usuario)->get(route($ruta))->assertRedirect(route('primer-login'));
        }

        $this->actingAs($usuario)->get(route('primer-login'))->assertOk();
    }

    public function test_el_cambio_inicial_exige_una_contrasena_nueva_y_segura(): void
    {
        $usuario = $this->usuarioConRol(Rol::Estudiante->value, ['primer_login' => true, 'password' => 'Temporal123']);

        Livewire::actingAs($usuario)->test(CambiarPasswordPrimerLogin::class)
            ->set('password', 'corta')->set('password_confirmation', 'corta')
            ->call('guardar')->assertHasErrors('password');

        Livewire::actingAs($usuario)->test(CambiarPasswordPrimerLogin::class)
            ->set('password', 'Temporal123')->set('password_confirmation', 'Temporal123')
            ->call('guardar')->assertHasErrors('password');

        $this->assertTrue($usuario->fresh()->primer_login);
    }

    public function test_el_cambio_inicial_no_escribe_la_contrasena_en_el_log(): void
    {
        Log::spy();
        $usuario = $this->usuarioConRol(Rol::Estudiante->value, ['primer_login' => true]);

        Livewire::actingAs($usuario)->test(CambiarPasswordPrimerLogin::class)
            ->set('password', 'NuevaClave2026')->set('password_confirmation', 'NuevaClave2026')
            ->call('guardar')
            ->assertRedirect(route('dashboard'));

        Log::shouldNotHaveReceived('info');
        $this->assertFalse($usuario->fresh()->primer_login);
        $this->assertTrue(Hash::check('NuevaClave2026', $usuario->fresh()->password));
    }

    public function test_los_campos_de_bloqueo_no_se_asignan_en_masa(): void
    {
        $usuario = User::factory()->create();

        $usuario->update(['intentos_fallidos' => 99, 'bloqueado_hasta' => now()->addYear()]);

        $this->assertSame(0, $usuario->fresh()->intentos_fallidos);
        $this->assertNull($usuario->fresh()->bloqueado_hasta);
    }

    public function test_se_puede_solicitar_la_recuperacion_de_contrasena(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_recuperar_la_contrasena_desbloquea_la_cuenta(): void
    {
        $usuario = $this->usuarioConRol(Rol::Estudiante->value, ['primer_login' => true]);

        foreach (range(1, 3) as $intento) {
            $this->intentarLogin($usuario->email, 'incorrecta');
        }
        $this->assertTrue($usuario->fresh()->estaBloqueada());

        Volt::test('pages.auth.reset-password', ['token' => Password::createToken($usuario)])
            ->set('email', $usuario->email)
            ->set('password', 'NuevaClave2026')
            ->set('password_confirmation', 'NuevaClave2026')
            ->call('resetPassword')
            ->assertHasNoErrors();

        $usuario->refresh();
        $this->assertFalse($usuario->estaBloqueada());
        $this->assertFalse($usuario->primer_login);
        $this->intentarLogin($usuario->email, 'NuevaClave2026')->assertHasNoErrors();
        $this->assertAuthenticatedAs($usuario);
    }
}
