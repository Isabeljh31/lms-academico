<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Auth\CambiarPasswordPrimerLogin;
use App\Livewire\Usuarios\Index;
use App\Mail\BienvenidaMail;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
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

    public function test_crea_usuario_con_dni_como_password_y_lo_envia_por_correo(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('abrirCrear')
            ->assertSee('Contraseña predeterminada: su DNI.')
            ->assertDontSee('autocomplete="new-password"', false)
            ->set('nuevoNombres', 'Ana')
            ->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '41239876')
            ->set('nuevoEmail', 'ana@example.com')
            ->call('crearUsuario')->assertHasNoErrors();

        $usuario = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('41239876', $usuario->password));
        $this->assertTrue($usuario->primer_login);
        Mail::assertSent(BienvenidaMail::class, fn ($mail) => $mail->passwordTemporal === '41239876' && $mail->hasTo('ana@example.com'));

        $this->actingAs($usuario)->get(route('cursos.index'))->assertRedirect(route('primer-login'));
        Livewire::actingAs($usuario)->test(CambiarPasswordPrimerLogin::class)
            ->set('password', 'ClavePersonal123!')
            ->set('password_confirmation', 'ClavePersonal123!')
            ->call('guardar')->assertHasNoErrors();

        $this->assertFalse($usuario->fresh()->primer_login);
        $this->assertTrue(Hash::check('ClavePersonal123!', $usuario->fresh()->password));
        $this->assertFalse(Hash::check('41239876', $usuario->fresh()->password));
    }

    public function test_editar_dni_no_cambia_la_password_existente(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $usuario = User::factory()->create(['primer_login' => false])->assignRole(Rol::Estudiante->value);
        $anterior = $usuario->password;

        Livewire::actingAs($admin)->test(Index::class)
            ->call('abrirEditar', $usuario->id)
            ->assertDontSee('autocomplete="new-password"', false)
            ->set('editarDni', '07123456')
            ->call('guardarEdicion')->assertHasNoErrors();

        $this->assertSame($anterior, $usuario->fresh()->password);
        $this->assertFalse($usuario->fresh()->primer_login);
    }

    public function test_elimina_cuenta_sin_historial_y_conserva_auditoria(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $usuario = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('eliminarCuenta', $usuario->id)->assertHasNoErrors();

        $this->assertModelMissing($usuario);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'eliminacion_cuenta', 'ejecutado_por' => $admin->id]);
    }

    public function test_no_elimina_cuenta_con_historial_ni_supervisor(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('eliminarCuenta', $profesor->id)->assertHasErrors('eliminar');
        Livewire::actingAs($admin)->test(Index::class)
            ->call('eliminarCuenta', $admin->id)->assertForbidden();

        $this->assertModelExists($profesor);
        $this->assertModelExists($curso);
        $this->assertModelExists($admin);
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

    public function test_corrige_el_rol_de_una_cuenta_sin_historial_y_lo_audita(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $usuario = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirEditar', $usuario->id)
            ->set('editarRol', Rol::Profesor->value)
            ->call('guardarEdicion')
            ->assertHasNoErrors();

        $this->assertSame([Rol::Profesor->value], $usuario->fresh()->getRoleNames()->all());
        $this->assertDatabaseHas('bitacoras_auditoria', [
            'tipo_evento' => 'cambio_rol',
            'usuario_afectado_id' => $usuario->id,
            'ejecutado_por' => $admin->id,
        ]);
    }

    public function test_no_permite_corregir_el_rol_de_una_cuenta_con_historial_academico(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        Curso::factory()->create(['profesor_id' => $profesor->id])->inscripciones()->create([
            'estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now(), 'origen' => 'manual', 'estado' => 'activo', 'activo_local' => true,
        ]);

        $componente = Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirEditar', $estudiante->id)
            ->set('editarRol', Rol::Profesor->value)
            ->call('guardarEdicion');

        $this->assertSame(
            'El rol ya no se puede cambiar: la cuenta tiene historial académico (cursos, matrículas, entregas o notas).',
            $componente->errors()->first('editarRol'),
        );
        $this->assertSame([Rol::Estudiante->value], $estudiante->fresh()->getRoleNames()->all());
        $this->assertDatabaseMissing('bitacoras_auditoria', ['tipo_evento' => 'cambio_rol']);
    }

    public function test_no_permite_convertir_una_cuenta_en_supervisor(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $usuario = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirEditar', $usuario->id)
            ->set('editarRol', Rol::Supervisor->value)
            ->call('guardarEdicion')
            ->assertHasErrors('editarRol');

        $this->assertSame([Rol::Estudiante->value], $usuario->fresh()->getRoleNames()->all());
    }

    public function test_el_supervisor_no_puede_corregir_su_propio_rol_ni_el_de_otro_supervisor(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $otroSupervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);

        $this->assertFalse(Gate::forUser($admin)->allows('corregirRol', $admin));
        $this->assertFalse(Gate::forUser($admin)->allows('corregirRol', $otroSupervisor));
    }

    public function test_un_profesor_no_puede_corregir_roles(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->assertFalse(Gate::forUser($profesor)->allows('corregirRol', $estudiante));
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
            ->set('nuevoDni', '45678123')
            ->set('nuevoEmail', 'nuevo@example.com')
            ->call('crearUsuario')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'nuevo@example.com')->firstOrFail();

        $this->assertSame('Nuevo Estudiante', $usuario->name);
        $this->assertTrue($usuario->hasRole(Rol::Estudiante->value));
        $this->assertTrue($usuario->primer_login);
        Mail::assertSent(BienvenidaMail::class, fn ($mail) => $mail->hasTo('nuevo@example.com'));
    }

    public function test_rechaza_un_correo_que_ya_existe_aunque_se_escriba_con_mayusculas(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        User::factory()->create(['email' => 'ana.torres@lms.edu.pe']);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Ana')
            ->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '45678124')
            ->set('nuevoEmail', 'Ana.Torres@LMS.edu.pe')
            ->call('crearUsuario')
            ->assertHasErrors(['nuevoEmail' => 'Ya existe una cuenta con este correo.']);

        $this->assertSame(1, User::where('email', 'ana.torres@lms.edu.pe')->count());
        Mail::assertNothingSent();
    }

    public function test_el_correo_de_una_cuenta_nueva_se_guarda_en_minusculas(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Luis')
            ->set('nuevoApellidos', 'Mendoza')
            ->set('nuevoDni', '45678125')
            ->set('nuevoEmail', 'Luis.Mendoza@LMS.edu.pe')
            ->call('crearUsuario')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'luis.mendoza@lms.edu.pe')->firstOrFail();

        $this->assertSame('Luis Mendoza', $usuario->name);
        Mail::assertSent(BienvenidaMail::class, fn ($mail) => $mail->hasTo('luis.mendoza@lms.edu.pe'));
    }

    public function test_la_contrasena_inicial_es_el_dni(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Ana')
            ->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '45678123')
            ->set('nuevoEmail', 'ana@lms.edu.pe')
            ->call('crearUsuario')
            ->assertHasNoErrors();

        $temporal = Mail::sent(BienvenidaMail::class)->first()->passwordTemporal;

        $this->assertSame('45678123', $temporal);
        $this->assertTrue(Hash::check($temporal, User::where('email', 'ana@lms.edu.pe')->value('password')));
    }

    public function test_guarda_nombres_apellidos_y_dni_por_separado(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', '  María   José ')
            ->set('nuevoApellidos', 'Pérez Salinas')
            ->set('nuevoDni', '07123456')
            ->set('nuevoEmail', 'maria.perez@lms.edu.pe')
            ->call('crearUsuario')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'maria.perez@lms.edu.pe')->firstOrFail();

        $this->assertSame('María José', $usuario->nombres);
        $this->assertSame('Pérez Salinas', $usuario->apellidos);
        $this->assertSame('07123456', $usuario->dni);
        $this->assertTrue(Hash::check('07123456', $usuario->password));
        $this->assertSame('María José Pérez Salinas', $usuario->name);
    }

    public function test_rechaza_nombres_con_numeros_o_simbolos_y_no_crea_la_cuenta(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        $componente = Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', '12345 <b>')
            ->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '45678123')
            ->set('nuevoEmail', 'raro@lms.edu.pe')
            ->call('crearUsuario');

        $this->assertSame(
            "Los nombres solo pueden tener letras y espacios (también se permite guion y apóstrofe, como en Pérez-Salinas u O'Brien).",
            $componente->errors()->first('nuevoNombres'),
        );
        $this->assertDatabaseMissing('users', ['email' => 'raro@lms.edu.pe']);
        Mail::assertNothingSent();
    }

    public function test_el_formulario_vacio_indica_en_espanol_cada_campo_obligatorio(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('crearUsuario')
            ->assertHasErrors([
                'nuevoNombres' => 'El campo nombres es obligatorio.',
                'nuevoApellidos' => 'El campo apellidos es obligatorio.',
                'nuevoDni' => 'El campo DNI es obligatorio.',
                'nuevoEmail' => 'El campo correo electrónico es obligatorio.',
            ]);
    }

    public function test_rechaza_un_dni_que_no_tiene_8_digitos(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Ana')
            ->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '1234567')
            ->set('nuevoEmail', 'ana@lms.edu.pe')
            ->call('crearUsuario')
            ->assertHasErrors(['nuevoDni' => 'El DNI debe tener exactamente 8 dígitos (solo números).']);

        $this->assertDatabaseMissing('users', ['email' => 'ana@lms.edu.pe']);
    }

    public function test_rechaza_un_dni_que_ya_pertenece_a_otra_cuenta(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        User::factory()->create(['dni' => '45678123']);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Ana')
            ->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '45678123')
            ->set('nuevoEmail', 'ana@lms.edu.pe')
            ->call('crearUsuario')
            ->assertHasErrors(['nuevoDni' => 'Ya existe una cuenta con este DNI.']);

        $this->assertDatabaseMissing('users', ['email' => 'ana@lms.edu.pe']);
    }

    public function test_editar_solo_los_apellidos_actualiza_el_nombre_completo_y_lo_audita(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $usuario = User::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Torez Vega', 'dni' => '45678123'])
            ->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirEditar', $usuario->id)
            ->assertSet('editarNombres', 'Ana')
            ->assertSet('editarApellidos', 'Torez Vega')
            ->set('editarApellidos', 'Torres Vega')
            ->call('guardarEdicion')
            ->assertHasNoErrors();

        $usuario->refresh();

        $this->assertSame('Ana', $usuario->nombres);
        $this->assertSame('Torres Vega', $usuario->apellidos);
        $this->assertSame('Ana Torres Vega', $usuario->name);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'edicion_datos_usuario', 'usuario_afectado_id' => $usuario->id]);
    }
}
