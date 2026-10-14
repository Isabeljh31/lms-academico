<?php

namespace Tests\Feature\Flujos;

use App\Enums\Rol;
use App\Livewire\Auth\CambiarPasswordPrimerLogin;
use App\Livewire\Cursos\BoletaEstudiante;
use App\Livewire\Cursos\EsquemaEvaluacion;
use App\Livewire\Cursos\Formulario as CursoFormulario;
use App\Livewire\Cursos\GestionMatricula;
use App\Livewire\Cursos\LibroCalificaciones;
use App\Livewire\Cursos\Show;
use App\Livewire\PanelAdmin\Configuracion;
use App\Livewire\Usuarios\Index as UsuariosIndex;
use App\Mail\BienvenidaMail;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\NotaRubro;
use App\Models\ResultadoQuiz;
use App\Models\User;
use App\Notifications\MatriculadoEnCurso;
use App\Notifications\NotaRubroRegistrada;
use App\Services\Evaluacion\CalculadoraNotas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Flujos principales de la sección 9 del ERS, de punta a punta.
 */
class FlujosPrincipalesTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    /**
     * 9.1: alta de cuenta, primer ingreso obligatorio, matrícula manual y
     * acceso del estudiante al curso (CU-01, CU-18, CU-19, CU-02).
     */
    public function test_flujo_alta_y_matricula_manual(): void
    {
        Mail::fake();
        Notification::fake();
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['supervisor'])->test(UsuariosIndex::class)
            ->call('abrirCrear')
            ->set('nuevoNombres', 'Lucía')
            ->set('nuevoApellidos', 'Ramos')
            ->set('nuevoDni', '45678123')
            ->set('nuevoEmail', 'lucia@instituto.edu.pe')
            ->call('crearUsuario')
            ->assertHasNoErrors();

        $lucia = User::where('email', 'lucia@instituto.edu.pe')->firstOrFail();
        $this->assertTrue($lucia->primer_login);
        Mail::assertSent(BienvenidaMail::class, fn ($mail) => $mail->hasTo('lucia@instituto.edu.pe'));

        // Primer ingreso: todo redirige al cambio de contraseña hasta completarlo.
        $this->actingAs($lucia)->get(route('cursos.index'))->assertRedirect(route('primer-login'));
        Livewire::actingAs($lucia)->test(CambiarPasswordPrimerLogin::class)
            ->set('password', 'MiClave2026')->set('password_confirmation', 'MiClave2026')
            ->call('guardar')->assertHasNoErrors();

        Livewire::actingAs($escenario['supervisor'])->test(GestionMatricula::class, ['curso' => $escenario['curso']])
            ->call('seleccionarEstudiante', $lucia->id)
            ->call('matricularEstudiante')
            ->assertHasNoErrors();

        Notification::assertSentTo($lucia, MatriculadoEnCurso::class, fn ($n, $canales) => $canales === ['database', 'mail']);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'matricula_manual', 'usuario_afectado_id' => $lucia->id]);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'creacion_cuenta', 'usuario_afectado_id' => $lucia->id]);

        $this->actingAs($lucia->fresh())->get(route('cursos.index'))->assertOk()->assertSee($escenario['curso']->nombre);
        $this->actingAs($lucia->fresh())->get(route('cursos.show', $escenario['curso']))->assertOk();
    }

    /**
     * 9.3: el Supervisor define el esquema al 100 %, el Profesor registra
     * notas y el estudiante ve su nota final ponderada (CU-20, CU-13, CU-06).
     */
    public function test_flujo_de_evaluacion_con_nota_final_ponderada(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['supervisor'])->test(EsquemaEvaluacion::class, ['curso' => $escenario['curso']])
            ->set('rubros', [
                ['id' => null, 'nombre' => 'Parcial', 'semana' => 4, 'peso' => 40],
                ['id' => null, 'nombre' => 'Final', 'semana' => 8, 'peso' => 60],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        [$parcial, $final] = $escenario['curso']->fresh()->esquemaEvaluacion->rubros->all();

        $libro = Livewire::actingAs($escenario['profesor'])->test(LibroCalificaciones::class, ['curso' => $escenario['curso']]);
        $libro->call('abrirModalNota', $parcial->id, $escenario['estudiante']->id)->set('notaInput', '8')->call('guardarNotaRubro')->assertHasNoErrors();
        $libro->call('abrirModalNota', $final->id, $escenario['estudiante']->id)->set('notaInput', '11')->call('guardarNotaRubro')->assertHasNoErrors();

        // 8 × 0,4 + 11 × 0,6 = 3,2 + 6,6 = 9,8 → bajo la mínima (10,5): en rojo.
        Livewire::actingAs($escenario['estudiante'])->test(BoletaEstudiante::class, ['curso' => $escenario['curso']])
            ->assertSee('9.8')
            ->assertSee('Desaprobado')
            ->assertSeeHtml('text-red-600');

        $libro->assertSee('9.8')->assertSeeHtml('bg-red-100');
    }

    public function test_registrar_una_nota_de_rubro_notifica_al_estudiante(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $rubro = $this->esquemaValido($escenario['curso'])->rubros->first();

        Livewire::actingAs($escenario['profesor'])->test(LibroCalificaciones::class, ['curso' => $escenario['curso']])
            ->call('abrirModalNota', $rubro->id, $escenario['estudiante']->id)
            ->set('notaInput', '16')
            ->call('guardarNotaRubro')
            ->assertHasNoErrors();

        Notification::assertSentTo($escenario['estudiante'], NotaRubroRegistrada::class);
    }

    public function test_el_esquema_no_se_guarda_si_los_pesos_no_suman_cien(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['supervisor'])->test(EsquemaEvaluacion::class, ['curso' => $escenario['curso']])
            ->set('rubros', [
                ['id' => null, 'nombre' => 'Parcial', 'semana' => 4, 'peso' => 40],
                ['id' => null, 'nombre' => 'Final', 'semana' => 8, 'peso' => 50],
            ])
            ->call('guardar')
            ->assertHasErrors('rubros')
            ->assertSee('Los pesos suman 90 %. Faltan 10 % para llegar a 100 %.');

        $this->assertSame(0, $escenario['curso']->esquemaEvaluacion()->first()->rubros()->count());
    }

    public function test_las_notas_de_tareas_y_quizzes_no_cambian_la_nota_final(): void
    {
        $escenario = $this->escenario();
        $rubros = $this->esquemaValido($escenario['curso'])->rubros;
        NotaRubro::create(['rubro_id' => $rubros[0]->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 10]);
        NotaRubro::create(['rubro_id' => $rubros[1]->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 10]);

        Entrega::create(['tarea_id' => $escenario['tarea']->id, 'estudiante_id' => $escenario['estudiante']->id, 'estado' => 'calificado', 'nota' => 20]);
        ResultadoQuiz::create(['quiz_id' => $escenario['quiz']->id, 'estudiante_id' => $escenario['estudiante']->id, 'respuestas' => [0, 1], 'puntaje' => 20]);

        $this->assertSame(10.0, app(CalculadoraNotas::class)->notaFinalDe($escenario['estudiante'], $escenario['curso']));
    }

    public function test_la_nota_minima_configurable_decide_la_aprobacion(): void
    {
        $escenario = $this->escenario();
        $rubros = $this->esquemaValido($escenario['curso'])->rubros;
        foreach ($rubros as $rubro) {
            NotaRubro::create(['rubro_id' => $rubro->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 12]);
        }

        Livewire::actingAs($escenario['supervisor'])->test(Configuracion::class)
            ->set('notaMinima', '13')
            ->call('guardar')
            ->assertHasNoErrors();

        Livewire::actingAs($escenario['estudiante'])->test(BoletaEstudiante::class, ['curso' => $escenario['curso']])
            ->assertSee('Desaprobado');
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'configuracion']);
    }

    /**
     * 9.4: clonación para un nuevo ciclo (CU-21). Copia contenido, tareas,
     * quizzes y esquema; nunca estudiantes, notas, entregas ni resultados.
     */
    public function test_flujo_de_clonacion_de_curso(): void
    {
        $escenario = $this->escenario();
        $rubros = $this->esquemaValido($escenario['curso'])->rubros;
        $escenario['modulo']->lecciones()->create(['titulo' => 'Lección 1', 'estado' => 'publicado', 'orden' => 1]);
        NotaRubro::create(['rubro_id' => $rubros[0]->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 15]);
        Entrega::create(['tarea_id' => $escenario['tarea']->id, 'estudiante_id' => $escenario['estudiante']->id, 'estado' => 'entregado', 'respuesta_texto' => 'x']);
        ResultadoQuiz::create(['quiz_id' => $escenario['quiz']->id, 'estudiante_id' => $escenario['estudiante']->id, 'respuestas' => [0, 1], 'puntaje' => 20]);
        $escenario['curso']->bancoPreguntasCurso()->create([
            'tema' => 'Álgebra', 'pregunta' => '¿2 + 2?', 'tipo' => 'opcion_unica', 'opciones' => ['3', '4'], 'respuestas_correctas' => [1],
        ]);

        Livewire::actingAs($escenario['supervisor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('abrirClonar')
            ->set('clonNombre', 'Curso de prueba 2027')
            ->set('clonCodigo', 'CP-2027')
            ->call('clonarCurso')
            ->assertHasNoErrors();

        $clon = Curso::where('codigo', 'CP-2027')->firstOrFail();

        $this->assertSame('borrador', $clon->estado);
        $this->assertSame(1, $clon->modulos()->count());
        $this->assertSame(1, $clon->modulos()->first()->lecciones()->count());
        $this->assertSame(1, $clon->tareas()->count());
        $this->assertSame(1, $clon->quizzes()->count());
        $this->assertSame(['Álgebra'], $clon->bancoPreguntasCurso()->pluck('tema')->all());
        $this->assertFalse($clon->tareas()->first()->esVisibleParaEstudiante());
        $this->assertEquals([40, 60], $clon->esquemaEvaluacion->rubros->pluck('peso')->map(fn ($p) => (float) $p)->all());

        $this->assertSame(0, $clon->inscripciones()->count());
        $this->assertSame(0, NotaRubro::whereIn('rubro_id', $clon->esquemaEvaluacion->rubros->pluck('id'))->count());
        $this->assertSame(0, Entrega::whereIn('tarea_id', $clon->tareas()->pluck('id'))->count());
        $this->assertSame(0, ResultadoQuiz::whereIn('quiz_id', $clon->quizzes()->pluck('id'))->count());

        // El curso original queda intacto.
        $this->assertSame(1, $escenario['curso']->inscripciones()->count());
        $this->assertSame(1, NotaRubro::count());
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'clonacion_curso', 'curso_id' => $clon->id]);
    }

    public function test_un_curso_archivado_no_admite_cambios_en_esquema_ni_notas(): void
    {
        $escenario = $this->escenario();
        $rubro = $this->esquemaValido($escenario['curso'])->rubros->first();
        $escenario['curso']->update(['estado' => 'archivado']);

        Livewire::actingAs($escenario['supervisor'])->test(EsquemaEvaluacion::class, ['curso' => $escenario['curso']])
            ->call('guardar')
            ->assertForbidden();

        Livewire::actingAs($escenario['profesor'])->test(LibroCalificaciones::class, ['curso' => $escenario['curso']])
            ->call('abrirModalNota', $rubro->id, $escenario['estudiante']->id)
            ->assertForbidden();
    }

    public function test_la_baja_es_logica_y_conserva_el_historial(): void
    {
        $escenario = $this->escenario();
        $rubro = $this->esquemaValido($escenario['curso'])->rubros->first();
        NotaRubro::create(['rubro_id' => $rubro->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 14]);
        $inscripcion = $escenario['curso']->inscripciones()->firstOrFail();

        Livewire::actingAs($escenario['supervisor'])->test(GestionMatricula::class, ['curso' => $escenario['curso']])
            ->call('confirmarBaja', $inscripcion->id)
            ->set('motivoBaja', 'Retiro voluntario')
            ->call('ejecutarBaja')
            ->assertHasNoErrors();

        $this->assertSame('inactivo', $inscripcion->fresh()->estado);
        $this->assertSame(1, NotaRubro::count());
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'baja_estudiante', 'motivo' => 'Retiro voluntario']);
    }

    // Matriz ERS § 5: matricular = solo Supervisor; dar de baja = Supervisor (todos) y Profesor (propio).
    public function test_el_profesor_no_puede_matricular(): void
    {
        $escenario = $this->escenario();
        $nuevo = $this->usuarioConRol(Rol::Estudiante->value);

        Livewire::actingAs($escenario['profesor'])->test(GestionMatricula::class, ['curso' => $escenario['curso']])
            ->call('seleccionarEstudiante', $nuevo->id)
            ->assertForbidden();

        $this->assertFalse($nuevo->estaMatriculadoEn($escenario['curso']));
    }

    public function test_el_profesor_da_de_baja_en_su_propio_curso_con_historial_y_auditoria(): void
    {
        Mail::fake();
        $escenario = $this->escenario();
        $inscripcion = $escenario['curso']->inscripciones()->first();

        Livewire::actingAs($escenario['profesor'])->test(GestionMatricula::class, ['curso' => $escenario['curso']])
            ->call('confirmarBaja', $inscripcion->id)
            ->set('motivoBaja', 'Retiro voluntario')
            ->call('ejecutarBaja')
            ->assertHasNoErrors();

        $this->assertFalse($inscripcion->fresh()->estaActiva());
        $this->assertDatabaseHas('inscripciones', ['id' => $inscripcion->id]);
        $this->assertDatabaseHas('bitacoras_auditoria', [
            'tipo_evento' => 'baja_estudiante',
            'ejecutado_por' => $escenario['profesor']->id,
            'motivo' => 'Retiro voluntario',
        ]);
    }

    public function test_el_profesor_no_da_de_baja_en_un_curso_ajeno(): void
    {
        $escenario = $this->escenario();
        $otroProfesor = $this->usuarioConRol(Rol::Profesor->value);

        Livewire::actingAs($otroProfesor)->test(GestionMatricula::class, ['curso' => $escenario['curso']])
            ->assertForbidden();
    }

    public function test_el_profesor_no_desactiva_una_matricula_que_llego_por_api(): void
    {
        $escenario = $this->escenario();
        $estudianteApi = $this->usuarioConRol(Rol::Estudiante->value);
        $this->matricular($estudianteApi, $escenario['curso'], ['origen' => 'api']);
        $inscripcionApi = $escenario['curso']->inscripciones()->where('estudiante_id', $estudianteApi->id)->firstOrFail();

        Livewire::actingAs($escenario['profesor'])->test(GestionMatricula::class, ['curso' => $escenario['curso']])
            ->call('confirmarBaja', $inscripcionApi->id)
            ->assertForbidden();

        $this->assertTrue($inscripcionApi->fresh()->estaActiva());
    }

    // ERS RF-23: uno o más profesores por curso.
    public function test_un_profesor_adicional_gestiona_y_califica_el_curso(): void
    {
        $escenario = $this->escenario();
        $coDocente = $this->usuarioConRol(Rol::Profesor->value);
        $escenario['curso']->profesoresAdicionales()->attach($coDocente);

        $this->assertTrue(Gate::forUser($coDocente)->allows('gestionar', $escenario['curso']));
        $this->assertTrue(Gate::forUser($coDocente)->allows('calificar', $escenario['curso']));
        $this->actingAs($coDocente)->get(route('cursos.tareas.crear', $escenario['curso']))->assertOk();
        $this->assertTrue(Curso::dictadosPor($coDocente)->whereKey($escenario['curso']->id)->exists());
    }

    public function test_el_supervisor_asigna_profesores_adicionales_y_queda_auditado(): void
    {
        $escenario = $this->escenario();
        $coDocente = $this->usuarioConRol(Rol::Profesor->value);
        $noProfesor = $this->usuarioConRol(Rol::Estudiante->value);

        Livewire::actingAs($escenario['supervisor'])->test(CursoFormulario::class, ['curso' => $escenario['curso']])
            ->set('profesoresAdicionales', [(string) $noProfesor->id])
            ->call('guardar')
            ->assertHasErrors('profesoresAdicionales');

        Livewire::actingAs($escenario['supervisor'])->test(CursoFormulario::class, ['curso' => $escenario['curso']])
            ->set('profesoresAdicionales', [(string) $coDocente->id, (string) $escenario['profesor']->id])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame([$coDocente->id], $escenario['curso']->profesoresAdicionales()->pluck('users.id')->all());
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'modificacion_curso', 'curso_id' => $escenario['curso']->id]);
    }

    // Matriz ERS § 5: el Supervisor consulta, pero no crea contenido ni registra notas.
    public function test_el_supervisor_consulta_el_curso_pero_no_gestiona_contenido_ni_notas(): void
    {
        $escenario = $this->escenario();
        $supervisor = $escenario['supervisor'];

        $this->actingAs($supervisor)->get(route('cursos.tareas.index', $escenario['curso']))->assertOk()->assertDontSee('Nueva tarea');
        $this->actingAs($supervisor)->get(route('cursos.libro-calificaciones', $escenario['curso']))->assertOk();
        $this->assertFalse(Gate::forUser($supervisor)->allows('gestionar', $escenario['curso']));
        $this->assertFalse(Gate::forUser($supervisor)->allows('calificar', $escenario['curso']));

        Livewire::actingAs($supervisor)->test(Show::class, ['curso' => $escenario['curso']])
            ->call('prepararSeccion', 2)
            ->assertForbidden();
    }

    public function test_desactivar_una_cuenta_conserva_su_historial(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['supervisor'])->test(UsuariosIndex::class)
            ->call('confirmarDesactivar', $escenario['estudiante']->id)
            ->call('desactivarCuenta');

        $this->assertFalse($escenario['estudiante']->fresh()->activo);
        $this->assertSame(1, $escenario['curso']->inscripciones()->count());
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'desactivacion_cuenta']);
    }
}
