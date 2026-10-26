<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Admin\Comunicados\Index as ComunicadosIndex;
use App\Livewire\Admin\Profesores\ImportarCsv;
use App\Livewire\Admin\Profesores\Index as ProfesoresIndex;
use App\Livewire\Admin\Profesores\Show as ProfesoresShow;
use App\Livewire\Cursos\Formulario as CursoFormulario;
use App\Mail\BienvenidaMail;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        $this->profesor = User::factory()->create(['name' => 'Profesor Test', 'anio_nacimiento' => 1990])->assignRole(Rol::Profesor->value);
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

    public function test_crea_profesor_con_dni_como_password(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('abrirCrear')->assertDontSee('autocomplete="new-password"', false)
            ->set('nuevoNombres', 'Ana')->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '41239876')->set('nuevoEmail', 'ana@example.com')
            ->set('nuevoAnioNacimiento', 1990)
            ->call('crearProfesor')->assertHasNoErrors();

        $usuario = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('41239876', $usuario->password));
        $this->assertTrue($usuario->hasRole(Rol::Profesor->value));
        Mail::assertSent(BienvenidaMail::class);
    }

    public function test_el_profesor_recibe_un_codigo_unico_automatico(): void
    {
        Mail::fake();
        $anio = now()->format('y');

        Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Ana')->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '41239876')->set('nuevoEmail', 'ana@example.com')
            ->set('nuevoAnioNacimiento', 1990)
            ->call('crearProfesor')->assertHasNoErrors();

        $this->assertSame("P-{$anio}-001", User::where('email', 'ana@example.com')->firstOrFail()->codigo);
    }

    public function test_registra_la_especialidad_del_profesor(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Ana')->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '41239876')->set('nuevoEmail', 'ana@example.com')
            ->set('nuevoAnioNacimiento', 1990)
            ->set('nuevaEspecialidad', 'Matemática y Física')
            ->call('crearProfesor')->assertHasNoErrors();

        $this->assertSame('Matemática y Física', User::where('email', 'ana@example.com')->firstOrFail()->especialidad);
    }

    public function test_la_especialidad_es_opcional_al_registrar_profesor(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Ana')->set('nuevoApellidos', 'Torres')
            ->set('nuevoDni', '41239876')->set('nuevoEmail', 'ana@example.com')
            ->set('nuevoAnioNacimiento', 1990)
            ->call('crearProfesor')->assertHasNoErrors();

        $this->assertNull(User::where('email', 'ana@example.com')->firstOrFail()->especialidad);
    }

    public function test_edita_la_especialidad_de_un_profesor(): void
    {
        $this->profesor->update(['especialidad' => 'Historia']);

        Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('abrirEditar', $this->profesor->id)
            ->assertSet('editarEspecialidad', 'Historia')
            ->set('editarEspecialidad', 'Historia y Geografía')
            ->call('guardarEdicion')->assertHasNoErrors();

        $this->assertSame('Historia y Geografía', $this->profesor->fresh()->especialidad);
    }

    public function test_edita_datos_del_profesor_y_conserva_password(): void
    {
        $anterior = $this->profesor->password;

        Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('abrirEditar', $this->profesor->id)
            ->assertDontSee('autocomplete="new-password"', false)
            ->set('editarNombres', 'Nombre Nuevo')
            ->call('guardarEdicion')->assertHasNoErrors();

        $this->assertSame('Nombre Nuevo', $this->profesor->fresh()->nombres);
        $this->assertSame($anterior, $this->profesor->fresh()->password);
    }

    public function test_desactiva_reactiva_y_elimina_profesor_sin_cursos(): void
    {
        $componente = Livewire::actingAs($this->admin)->test(ProfesoresIndex::class);
        $componente->call('cambiarEstado', $this->profesor->id)->assertHasNoErrors();
        $this->assertFalse($this->profesor->fresh()->activo);
        $componente->call('cambiarEstado', $this->profesor->id)->assertHasNoErrors();
        $this->assertTrue($this->profesor->fresh()->activo);
        $componente->call('eliminarProfesor', $this->profesor->id)->assertHasNoErrors();
        $this->assertModelMissing($this->profesor);
    }

    public function test_no_elimina_profesor_con_cursos(): void
    {
        $curso = Curso::factory()->create(['profesor_id' => $this->profesor->id]);

        Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('eliminarProfesor', $this->profesor->id)->assertHasErrors('eliminar');

        $this->assertModelExists($this->profesor);
        $this->assertModelExists($curso);
    }

    public function test_directorio_no_permite_editar_estudiantes(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('abrirEditar', $this->estudiante->id);
    }

    public function test_directorio_no_permite_eliminar_estudiantes(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('eliminarProfesor', $this->estudiante->id);
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
            ->set('nuevoAnioNacimiento', 1990)
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

    public function test_desactivar_un_profesor_pide_confirmacion_antes_de_aplicar(): void
    {
        $componente = Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('confirmarCambiarEstado', $this->profesor->id)
            ->assertSet('mostrarConfirmacion', true)
            ->assertSet('confirmacionAccion', 'cambiarEstado');

        $this->assertTrue($this->profesor->fresh()->activo);

        $componente->call('ejecutarConfirmacion')->assertSet('mostrarConfirmacion', false);

        $this->assertFalse($this->profesor->fresh()->activo);
    }

    public function test_eliminar_un_profesor_pide_confirmacion_antes_de_aplicar(): void
    {
        $componente = Livewire::actingAs($this->admin)->test(ProfesoresIndex::class)
            ->call('confirmarEliminarProfesor', $this->profesor->id)
            ->assertSet('mostrarConfirmacion', true)
            ->assertSet('confirmacionAccion', 'eliminarProfesor');

        $this->assertModelExists($this->profesor);

        $componente->call('ejecutarConfirmacion');

        $this->assertModelMissing($this->profesor);
    }

    public function test_reasignar_un_curso_con_profesor_pide_confirmacion(): void
    {
        $curso = Curso::factory()->create(['profesor_id' => $this->admin->id, 'nombre' => 'Curso con titular']);
        $curso->update(['profesor_id' => $this->profesor->id]);

        $otroProfesor = User::factory()->create(['anio_nacimiento' => 1990])->assignRole(Rol::Profesor->value);

        $componente = Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->call('abrirModalAsignar', $otroProfesor->id)
            ->set('cursoAAsignarId', $curso->id)
            ->call('confirmarAsignarCurso')
            ->assertSet('mostrarConfirmacion', true)
            ->assertSet('confirmacionAccion', 'asignarCurso');

        $this->assertSame($this->profesor->id, $curso->fresh()->profesor_id);

        $componente->call('ejecutarConfirmacion');

        $this->assertSame($otroProfesor->id, $curso->fresh()->profesor_id);
    }

    public function test_asignar_un_curso_sin_profesor_no_pide_confirmacion(): void
    {
        $curso = Curso::factory()->create(['profesor_id' => $this->admin->id, 'nombre' => 'Curso huérfano dos']);

        Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->call('abrirModalAsignar', $this->profesor->id)
            ->set('cursoAAsignarId', $curso->id)
            ->call('confirmarAsignarCurso')
            ->assertSet('mostrarConfirmacion', false);

        $this->assertSame($this->profesor->id, $curso->fresh()->profesor_id);
    }

    public function test_desvincular_un_profesor_de_un_curso_pide_confirmacion(): void
    {
        $curso = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'nombre' => 'Curso a desvincular']);

        $componente = Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->call('confirmarDesasignarCurso', $curso->id)
            ->assertSet('mostrarConfirmacion', true)
            ->assertSet('confirmacionAccion', 'desasignarCurso');

        $this->assertSame($this->profesor->id, $curso->fresh()->profesor_id);

        $componente->call('ejecutarConfirmacion');

        $this->assertSame($this->admin->id, $curso->fresh()->profesor_id);
    }

    public function test_administrador_puede_asignar_profesor_a_un_curso_sin_profesor_desde_la_lista(): void
    {
        $curso = Curso::factory()->create(['profesor_id' => $this->admin->id, 'nombre' => 'Curso huérfano']);

        Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->assertDontSee('Curso huérfano')
            ->call('toggleCursosSinProfesor')
            ->assertSee('Curso huérfano')
            ->call('abrirModalAsignarDesdeCurso', $curso->id)
            ->assertSet('asignarPorCurso', true)
            ->assertSet('cursoAAsignarId', $curso->id)
            ->set('profesorParaAsignarId', $this->profesor->id)
            ->call('asignarCurso')
            ->assertHasNoErrors();

        $this->assertSame($this->profesor->id, $curso->fresh()->profesor_id);
    }

    public function test_ordena_el_directorio_por_atrasos_de_calificacion(): void
    {
        $puntual = User::factory()->create(['name' => 'Profesor Puntual', 'anio_nacimiento' => 1990])->assignRole(Rol::Profesor->value);
        $atrasado = User::factory()->create(['name' => 'Profesor Atrasado', 'anio_nacimiento' => 1990])->assignRole(Rol::Profesor->value);

        $cursoAtrasado = Curso::factory()->create(['profesor_id' => $atrasado->id]);
        $tarea = Tarea::factory()->create(['curso_id' => $cursoAtrasado->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        Entrega::factory()->create([
            'tarea_id' => $tarea->id,
            'estudiante_id' => $estudiante->id,
            'estado' => 'entregado',
            'created_at' => now()->subDays(5),
        ]);

        $html = Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->set('orden', 'atrasos')
            ->html();

        $this->assertGreaterThan(
            strpos($html, 'Profesor Atrasado'),
            strpos($html, 'Profesor Puntual'),
            'El profesor con entregas atrasadas debe listarse antes.'
        );
        $this->assertNotFalse(strpos($html, $puntual->name));
    }

    public function test_filtra_el_directorio_por_estado(): void
    {
        $this->profesor->update(['activo' => false]);

        Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->set('filtroEstado', 'activo')
            ->assertDontSee($this->profesor->name)
            ->set('filtroEstado', 'inactivo')
            ->assertSee($this->profesor->name);
    }

    public function test_la_busqueda_del_directorio_incluye_la_especialidad(): void
    {
        $this->profesor->update(['especialidad' => 'Química Orgánica']);

        Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->set('busqueda', 'Química Orgánica')
            ->assertSee($this->profesor->name);
    }

    public function test_la_busqueda_del_directorio_ignora_mayusculas_y_tildes(): void
    {
        $this->profesor->update(['especialidad' => 'Matemática']);

        Livewire::actingAs($this->admin)
            ->test(ProfesoresIndex::class)
            ->set('busqueda', 'matematicas')
            ->assertSee($this->profesor->name);
    }

    public function test_la_ficha_de_profesor_separa_cursos_activos_y_archivados(): void
    {
        $activo = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'nombre' => 'Curso vigente', 'estado' => 'publicado']);
        $archivado = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'nombre' => 'Curso viejo', 'estado' => 'archivado']);

        $this->actingAs($this->admin)
            ->get(route('admin.profesores.show', $this->profesor))
            ->assertOk()
            ->assertSee('Curso vigente')
            ->assertSee('Curso viejo')
            ->assertSee('Historial');

        Livewire::actingAs($this->admin)->test(ProfesoresShow::class, ['profesor' => $this->profesor])
            ->assertSet('mostrarModalAsignar', false);

        $this->assertTrue($activo->exists);
        $this->assertTrue($archivado->estaFinalizado());
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
            ->set('modalidad', 'virtual')
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

    public function test_importa_profesores_desde_csv_crea_filas_validas_y_reporta_errores(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'existe@x.pe']);

        $csv = "nombres;apellidos;dni;correo;anio_nacimiento;especialidad\n"
            ."Ana;García;45678123;ana@x.pe;1988;Matemática\n"
            ."Luis;Pérez;45678124;correo-malo;1990;Historia\n"
            ."Sin;Anio;45678125;sinanio@x.pe;;Arte\n"
            ."Eva;Ruiz;45678126;existe@x.pe;1992;Química\n"
            ."Ana;Copia;45678127;ana@x.pe;1988;Matemática\n";

        $componente = Livewire::actingAs($this->admin)->test(ImportarCsv::class)
            ->set('archivo', UploadedFile::fake()->createWithContent('profesores.csv', $csv))
            ->call('importar');

        $this->assertCount(1, $componente->get('creados'));
        $this->assertCount(1, $componente->get('omitidos'));
        $this->assertCount(3, $componente->get('errores'));

        $creado = User::where('email', 'ana@x.pe')->firstOrFail();
        $this->assertTrue($creado->hasRole(Rol::Profesor->value));
        $this->assertSame('Matemática', $creado->especialidad);
        $this->assertEquals(1988, $creado->anio_nacimiento);
        $this->assertTrue($creado->primer_login);
        $this->assertNotNull($creado->codigo);
    }

    public function test_importar_profesores_requiere_la_columna_anio_nacimiento(): void
    {
        $csv = "nombres;apellidos;dni;correo\nAna;García;45678123;ana@x.pe\n";

        Livewire::actingAs($this->admin)->test(ImportarCsv::class)
            ->set('archivo', UploadedFile::fake()->createWithContent('profesores.csv', $csv))
            ->call('importar')
            ->assertHasErrors('archivo');

        $this->assertFalse(User::where('email', 'ana@x.pe')->exists());
    }

    public function test_profesor_y_estudiante_no_pueden_importar_profesores(): void
    {
        $this->actingAs($this->profesor)
            ->get(route('admin.profesores.importar'))
            ->assertForbidden();

        $this->actingAs($this->estudiante)
            ->get(route('admin.profesores.importar'))
            ->assertForbidden();
    }
}
