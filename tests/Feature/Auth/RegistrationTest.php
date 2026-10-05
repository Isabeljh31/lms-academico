<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ERS 2.4: no existe registro público ni autoservicio de cuentas. Estas
 * pruebas reemplazan a las de Breeze, que verificaban lo contrario.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ruta_de_registro_publico_no_existe(): void
    {
        $this->assertFalse(Route::has('register'));

        $this->get('/register')->assertNotFound();
    }

    public function test_no_se_puede_crear_una_cuenta_enviando_datos_a_register(): void
    {
        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(404);

        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }

    public function test_la_pantalla_de_login_no_enlaza_a_registro(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('/register');
    }
}
