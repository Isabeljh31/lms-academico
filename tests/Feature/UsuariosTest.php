<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Auth\CambiarPasswordPrimerLogin;
use App\Livewire\Compartido\Cursos\GestionMatricula;
use App\Livewire\Supervisor\Estudiantes\Index;
use App\Mail\BienvenidaMail;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Facultad;
use App\Models\Inscripcion;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\Sede;
use App\Models\SesionClase;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Reportes\ProgresoAcademicoService;
use App\Notifications\MatriculadoEnCurso;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
            ->get(route('supervisor.estudiantes.index'))
            ->assertOk();
    }

    public function test_estudiante_recien_creado_aparece_en_busqueda_de_curso_nuevo(): void
    {
        Mail::fake();
        Notification::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)->test(Index::class)
            ->set('nuevoNombres', 'Juan Carlos')
            ->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '07123456')
            ->set('nuevoEmail', 'juan.torres@example.com')
            ->call('crearUsuario')->assertHasNoErrors();

        $estudiante = User::where('email', 'juan.torres@example.com')->firstOrFail();
        $curso = Curso::factory()->create(['profesor_id' => $admin->id]);
        $componente = Livewire::actingAs($admin)->test(GestionMatricula::class, ['curso' => $curso]);
        foreach (['juan', 'JUAN.TORRES@EXAMPLE.COM', ' 07123456 '] as $busqueda) {
            $componente->set('busquedaEstudiante', $busqueda)
                ->assertViewHas('estudiantesBusqueda', fn ($resultados) => $resultados->modelKeys() === [$estudiante->id]);
        }

        $componente->call('seleccionarEstudiante', $estudiante->id)
            ->call('matricularEstudiante')->assertHasNoErrors();

        $this->assertDatabaseHas('inscripciones', ['curso_id' => $curso->id, 'estudiante_id' => $estudiante->id, 'estado' => 'activo']);
        Mail::assertSent(BienvenidaMail::class);
        Notification::assertSentTo($estudiante, MatriculadoEnCurso::class);
    }

    public function test_busqueda_matricula_excluye_inactivos_profesores_y_ya_matriculados(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->create(['profesor_id' => $admin->id]);
        $disponible = User::factory()->create(['name' => 'Juan Disponible'])->assignRole(Rol::Estudiante->value);
        User::factory()->create(['name' => 'Juan Inactivo', 'activo' => false])->assignRole(Rol::Estudiante->value);
        User::factory()->create(['name' => 'Juan Profesor'])->assignRole(Rol::Profesor->value);
        $matriculado = User::factory()->create(['name' => 'Juan Matriculado'])->assignRole(Rol::Estudiante->value);
        Inscripcion::factory()->create(['curso_id' => $curso->id, 'estudiante_id' => $matriculado->id]);

        Livewire::actingAs($admin)->test(GestionMatricula::class, ['curso' => $curso])
            ->set('busquedaEstudiante', 'juan')
            ->assertViewHas('estudiantesBusqueda', fn ($resultados) => $resultados->modelKeys() === [$disponible->id]);
    }

    public function test_crea_usuario_con_dni_como_password_y_lo_envia_por_correo(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('abrirCrear')
            ->assertSee('Contraseña predeterminada: el DNI del usuario.')
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

    public function test_no_elimina_un_estudiante_con_historial_academico(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        Inscripcion::factory()->create(['curso_id' => $curso->id, 'estudiante_id' => $estudiante->id]);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('eliminarCuenta', $estudiante->id)->assertHasErrors('eliminar');

        $this->assertModelExists($estudiante);
    }

    public function test_el_directorio_de_estudiantes_no_permite_eliminar_a_un_profesor(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($admin)->test(Index::class)->call('eliminarCuenta', $profesor->id);

        $this->assertModelExists($profesor);
    }

    public function test_profesor_no_puede_acceder_al_listado_de_usuarios(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        $this->actingAs($profesor)
            ->get(route('supervisor.estudiantes.index'))
            ->assertForbidden();
    }

    public function test_estudiante_no_puede_acceder_al_listado_de_usuarios(): void
    {
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($estudiante)
            ->get(route('supervisor.estudiantes.index'))
            ->assertForbidden();
    }

    public function test_invitado_es_redirigido_a_login(): void
    {
        $this->get(route('supervisor.estudiantes.index'))
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

    public function test_crea_un_estudiante_con_datos_del_apoderado(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)->test(Index::class)
            ->set('nuevoNombres', 'Luis')
            ->set('nuevoApellidos', 'Ramos')
            ->set('nuevoDni', '45678130')
            ->set('nuevoEmail', 'luis.ramos@example.com')
            ->set('nuevoAnioNacimiento', 2014)
            ->set('nuevoCelular', '987654321')
            ->set('nuevoApoderadoNombre', 'Rosa Vela')
            ->set('nuevoApoderadoCelular', '912345678')
            ->call('crearUsuario')
            ->assertHasNoErrors();

        $estudiante = User::where('email', 'luis.ramos@example.com')->firstOrFail();
        $this->assertTrue($estudiante->hasRole(Rol::Estudiante->value));
        $this->assertEquals(2014, $estudiante->anio_nacimiento);
        $this->assertSame('987654321', $estudiante->celular);
        $this->assertSame('Rosa Vela', $estudiante->apoderado_nombre);
        $this->assertSame('912345678', $estudiante->apoderado_celular);
    }

    public function test_los_datos_del_apoderado_son_opcionales_al_crear_un_estudiante(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        Livewire::actingAs($admin)->test(Index::class)
            ->set('nuevoNombres', 'Diego')
            ->set('nuevoApellidos', 'Flores')
            ->set('nuevoDni', '45678131')
            ->set('nuevoEmail', 'diego.flores@example.com')
            ->call('crearUsuario')
            ->assertHasNoErrors();

        $estudiante = User::where('email', 'diego.flores@example.com')->firstOrFail();
        $this->assertNull($estudiante->anio_nacimiento);
        $this->assertNull($estudiante->apoderado_nombre);
        $this->assertNull($estudiante->apoderado_celular);
    }

    public function test_el_registro_de_estudiante_no_exige_mayoria_de_edad(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $anioActual = (int) now()->format('Y');

        Livewire::actingAs($admin)->test(Index::class)
            ->set('nuevoNombres', 'Mia')
            ->set('nuevoApellidos', 'Castro')
            ->set('nuevoDni', '45678132')
            ->set('nuevoEmail', 'mia.castro@example.com')
            ->set('nuevoAnioNacimiento', $anioActual - 6)
            ->call('crearUsuario')
            ->assertHasNoErrors();

        $estudiante = User::where('email', 'mia.castro@example.com')->firstOrFail();
        $this->assertEquals($anioActual - 6, $estudiante->anio_nacimiento);
    }

    public function test_marca_riesgo_alto_cuando_hay_mas_de_un_curso_critico_por_asistencia(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create(['name' => 'Estudiante Ausente'])->assignRole(Rol::Estudiante->value);

        foreach (range(1, 2) as $n) {
            $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'usa_asistencia' => true, 'estado' => 'publicado']);
            Inscripcion::factory()->create(['curso_id' => $curso->id, 'estudiante_id' => $estudiante->id]);
            // Sesión pasada sin registro de asistencia: cuenta como ausente (0%).
            SesionClase::create([
                'curso_id' => $curso->id,
                'titulo' => "Clase {$n}",
                'fecha' => now()->subDay(),
                'duracion_minutos' => 60,
                'numero_sesion' => 1,
            ]);
        }

        $html = Livewire::actingAs($admin)->test(Index::class)->html();

        $this->assertStringContainsString('Riesgo alto', $html);
    }

    public function test_el_directorio_no_hace_mas_consultas_con_mas_estudiantes(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->create(['usa_asistencia' => true, 'estado' => 'publicado']);
        SesionClase::create(['curso_id' => $curso->id, 'titulo' => 'Clase 1', 'fecha' => now()->subDay(), 'duracion_minutos' => 60, 'numero_sesion' => 1]);
        $matricular = function (int $cantidad) use ($curso): void {
            foreach (range(1, $cantidad) as $_) {
                $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
                Inscripcion::factory()->create(['curso_id' => $curso->id, 'estudiante_id' => $estudiante->id]);
            }
        };
        $consultasAlMostrar = function () use ($admin): int {
            $consultas = 0;
            DB::listen(function () use (&$consultas) {
                $consultas++;
            });
            Livewire::actingAs($admin)->test(Index::class);

            return $consultas;
        };

        // Una página completa (8 filas) desde el inicio: lo que cambia después es solo la cantidad total.
        $matricular(8);
        $consultasAlMostrar();
        $conOcho = $consultasAlMostrar();

        $matricular(8);

        $this->assertSame($conOcho, $consultasAlMostrar());
    }

    public function test_las_tareas_pendientes_del_directorio_coinciden_con_las_del_panel_del_estudiante(): void
    {
        $curso = Curso::factory()->create(['estado' => 'publicado']);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $tareas = Tarea::factory()->count(2)->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'fecha_entrega' => now()->addWeek()]);
        Quiz::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'fecha_inicio' => now()->subDay(), 'fecha_limite' => now()->addDay()]);
        Quiz::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'fecha_inicio' => now()->subWeek(), 'fecha_limite' => now()->subDay()]);
        $conEntrega = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $sinEntrega = User::factory()->create()->assignRole(Rol::Estudiante->value);
        foreach ([$conEntrega, $sinEntrega] as $estudiante) {
            Inscripcion::factory()->create(['curso_id' => $curso->id, 'estudiante_id' => $estudiante->id]);
        }
        Entrega::factory()->create(['tarea_id' => $tareas[0]->id, 'estudiante_id' => $conEntrega->id]);

        $progreso = app(ProgresoAcademicoService::class);
        $enLote = $progreso->totalPendientesDe(collect([$conEntrega->id, $sinEntrega->id]));

        // 2 tareas + 1 quiz abierto sin intento; quien entregó una tarea tiene una pendiente menos.
        $this->assertSame([$conEntrega->id => 2, $sinEntrega->id => 3], $enLote);
        foreach ([$conEntrega, $sinEntrega] as $estudiante) {
            $this->assertSame($progreso->resumenDashboardEstudiante($estudiante)['total_pendientes'], $enLote[$estudiante->id]);
        }
    }

    public function test_con_facultades_los_estudiantes_se_listan_por_sede_y_facultad_y_aparte_los_virtuales(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $sede = Sede::factory()->create();
        $facultad = Facultad::factory()->create(['sede_id' => $sede->id]);
        $presencial = Curso::factory()->create(['modalidad' => 'presencial', 'sede_id' => $sede->id, 'facultad_id' => $facultad->id]);
        $virtual = Curso::factory()->create(['modalidad' => 'virtual', 'facultad_id' => $facultad->id]);
        foreach (['Alumna Presencial' => $presencial, 'Alumno Virtual' => $virtual, 'Alumno Sin Matricula' => null] as $nombre => $curso) {
            $estudiante = User::factory()->create(['name' => $nombre])->assignRole(Rol::Estudiante->value);
            if ($curso) {
                Inscripcion::factory()->create(['curso_id' => $curso->id, 'estudiante_id' => $estudiante->id]);
            }
        }

        Livewire::actingAs($admin)->test(Index::class)
            ->assertSee('Elige la sede y la facultad')
            ->assertDontSee('Alumna Presencial')
            ->set('sedeUbicacion', (string) $sede->id)->set('facultadUbicacion', (string) $facultad->id)
            ->assertSee('Alumna Presencial')
            ->assertDontSee('Alumno Virtual')
            ->call('verVistaUbicacion', 'virtual')
            ->assertSee('Alumno Virtual')
            ->assertDontSee('Alumna Presencial')
            ->call('verVistaUbicacion', 'sin_cursos')
            ->assertSee('Alumno Sin Matricula')
            ->assertDontSee('Alumno Virtual');
    }

    public function test_el_estudiante_aparece_por_su_sede_propia_y_por_cada_sede_donde_cursa(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        [$olivos, $comas] = Sede::factory()->count(2)->create()->all();
        $ingOlivos = Facultad::factory()->create(['sede_id' => $olivos->id]);
        $saludComas = Facultad::factory()->create(['sede_id' => $comas->id]);
        $cursoComas = Curso::factory()->create(['modalidad' => 'presencial', 'sede_id' => $comas->id, 'facultad_id' => $saludComas->id]);
        // Su sede propia es Los Olivos, pero también lleva un curso en Comas.
        $viajera = User::factory()->create(['name' => 'Alumna Dos Sedes', 'sede_id' => $olivos->id, 'facultad_id' => $ingOlivos->id])->assignRole(Rol::Estudiante->value);
        Inscripcion::factory()->create(['curso_id' => $cursoComas->id, 'estudiante_id' => $viajera->id]);

        Livewire::actingAs($admin)->test(Index::class)
            ->set('sedeUbicacion', (string) $olivos->id)->set('facultadUbicacion', (string) $ingOlivos->id)
            ->assertSee('Alumna Dos Sedes')
            ->set('sedeUbicacion', (string) $comas->id)->set('facultadUbicacion', (string) $saludComas->id)
            ->assertSee('Alumna Dos Sedes');
    }

    public function test_al_crear_un_estudiante_con_facultades_se_eligen_su_sede_y_facultad(): void
    {
        Mail::fake();
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $sede = Sede::factory()->create();
        $facultad = Facultad::factory()->create(['sede_id' => $sede->id]);
        $deOtraSede = Facultad::factory()->create();

        $formulario = Livewire::actingAs($admin)->test(Index::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Lucía')->set('nuevoApellidos', 'Ramos Díaz')->set('nuevoDni', '45678912')->set('nuevoEmail', 'lucia.ramos@example.com')
            ->call('crearUsuario')
            ->assertHasErrors(['nuevoSedeId' => 'required', 'nuevoFacultadId' => 'required'])
            ->set('nuevoSedeId', (string) $sede->id)->set('nuevoFacultadId', (string) $deOtraSede->id)
            ->call('crearUsuario')
            ->assertHasErrors(['nuevoFacultadId' => 'exists']);

        $formulario->set('nuevoFacultadId', (string) $facultad->id)->call('crearUsuario')->assertHasNoErrors();

        $estudiante = User::where('email', 'lucia.ramos@example.com')->sole();
        $this->assertSame([$sede->id, $facultad->id], [$estudiante->sede_id, $estudiante->facultad_id]);
    }

    /**
     * La ficha de un estudiante recalculaba asistencia y rendimiento dos veces
     * por curso (una vez para mostrarlos, otra dentro de calcularRiesgo) y
     * volvía a traer tareas/quizzes/cursos desde cero dentro de
     * resumenDashboardEstudiante(). Con suficientes cursos reales esto superaba
     * el límite de tiempo de PHP. Ahora cada curso agrega una cantidad acotada
     * de consultas, no duplicada.
     */
    public function test_la_ficha_de_estudiante_no_duplica_consultas_por_cada_curso(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $consultasCon = function (int $cantidadCursos) use ($admin, $profesor, $estudiante): int {
            Curso::factory()->count($cantidadCursos)->create(['profesor_id' => $profesor->id, 'usa_asistencia' => true, 'estado' => 'publicado'])
                ->each(fn (Curso $curso) => Inscripcion::factory()->create(['curso_id' => $curso->id, 'estudiante_id' => $estudiante->id]));

            $medir = fn () => $this->actingAs($admin)->get(route('supervisor.estudiantes.show', $estudiante));
            $medir();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $medir();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $conUno = $consultasCon(1);
        $conCuatroMas = $consultasCon(4);

        // Cada curso adicional agrega una cantidad acotada de consultas (no el
        // doble, como antes de este arreglo): con 4 cursos más, no debería
        // sumar más de 15 consultas por curso.
        $this->assertLessThanOrEqual($conUno + 4 * 15, $conCuatroMas);
    }

    public function test_la_ficha_de_estudiante_distingue_curso_finalizado_de_dado_de_baja(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $cursoFinalizado = Curso::factory()->create(['profesor_id' => $profesor->id, 'nombre' => 'Curso finalizado', 'estado' => 'archivado']);
        Inscripcion::factory()->create(['curso_id' => $cursoFinalizado->id, 'estudiante_id' => $estudiante->id]);

        $cursoConBaja = Curso::factory()->create(['profesor_id' => $profesor->id, 'nombre' => 'Curso con baja', 'estado' => 'publicado']);
        Inscripcion::factory()->deBaja()->create(['curso_id' => $cursoConBaja->id, 'estudiante_id' => $estudiante->id, 'dado_de_baja_por' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('supervisor.estudiantes.show', $estudiante))
            ->assertOk()
            ->assertSee('Curso finalizado')
            ->assertSee('Finalizado')
            ->assertSee('Curso con baja')
            ->assertSee('Dado de baja')
            ->assertSee($admin->name);
    }

    public function test_la_ficha_de_estudiante_muestra_datos_del_apoderado(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $estudiante->update(['apoderado_nombre' => 'Rosa Vela', 'apoderado_celular' => '912345678']);

        $this->actingAs($admin)
            ->get(route('supervisor.estudiantes.show', $estudiante))
            ->assertOk()
            ->assertSee('Rosa Vela')
            ->assertSee('912345678');
    }

    public function test_el_directorio_de_estudiantes_no_permite_editar_a_un_profesor(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($admin)->test(Index::class)->call('abrirEditar', $profesor->id);
    }
}
