<?php

namespace Tests\Feature\Flujos;

use App\Enums\Rol;
use App\Livewire\Compartido\Asistencia\Index as AsistenciaIndex;
use App\Livewire\Compartido\Calendario\Index as Calendario;
use App\Livewire\Compartido\Cursos\Show;
use App\Livewire\Compartido\Grupos\Index as GruposIndex;
use App\Livewire\Compartido\Mensajeria\Bandeja;
use App\Livewire\Compartido\Quizzes\Formulario as QuizFormulario;
use App\Livewire\Compartido\Quizzes\Resolver;
use App\Livewire\Compartido\Tareas\Detalle;
use App\Livewire\Supervisor\Comunicados\Index as Comunicados;
use App\Livewire\Supervisor\Estudiantes\ImportarCSV;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Mensaje;
use App\Models\NotaRubro;
use App\Models\Quiz;
use App\Models\ResultadoQuiz;
use App\Models\Tarea;
use App\Models\User;
use App\Notifications\CambioFechaQuiz;
use App\Notifications\EntregaDevuelta;
use App\Notifications\NotaQuizModificada;
use App\Notifications\NuevaTareaAsignada;
use App\Notifications\NuevoComunicado;
use App\Notifications\NuevoContenidoPublicado;
use App\Notifications\NuevoMensaje;
use App\Services\HojasCalculo\LibroXlsx;
use App\Services\Reportes\ProgresoAcademicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Casos de uso CU-03 a CU-17 y CU-22 del ERS.
 */
class CasosDeUsoTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    // CU-11 / RF-35 a RF-38
    public function test_el_contenido_en_borrador_o_programado_no_es_visible_para_el_estudiante(): void
    {
        $escenario = $this->escenario();
        $escenario['modulo']->lecciones()->create(['titulo' => 'Lección Publicada', 'estado' => 'publicado', 'orden' => 1]);
        $escenario['modulo']->lecciones()->create(['titulo' => 'Lección Borrador', 'estado' => 'borrador', 'orden' => 2]);
        $escenario['modulo']->lecciones()->create(['titulo' => 'Lección Futura', 'estado' => 'programado', 'fecha_publicacion' => now()->addDay(), 'orden' => 3]);
        $escenario['tarea']->update(['titulo' => 'Tarea Programada', 'estado_contenido' => 'programado', 'publicar_en' => now()->addDay()]);

        $this->actingAs($escenario['estudiante'])->get(route('cursos.show', $escenario['curso']))
            ->assertSee('Lección Publicada')
            ->assertDontSee('Lección Borrador')
            ->assertDontSee('Lección Futura')
            ->assertDontSee('Tarea Programada');

        $this->actingAs($escenario['estudiante'])->get(route('cursos.tareas.show', [$escenario['curso'], $escenario['tarea']]))->assertForbidden();

        $this->travel(2)->days();
        $this->actingAs($escenario['estudiante'])->get(route('cursos.tareas.show', [$escenario['curso'], $escenario['tarea']]))->assertOk();
    }

    public function test_el_estudiante_ve_la_fecha_de_vencimiento_real_de_sus_tareas(): void
    {
        $escenario = $this->escenario();
        $escenario['tarea']->update(['fecha_entrega' => now()->addDays(3)->setTime(18, 30)]);

        $this->actingAs($escenario['estudiante'])->get(route('cursos.show', $escenario['curso']))
            ->assertSee('18:30')
            ->assertDontSee('Sin fecha límite');
    }

    public function test_publicar_una_leccion_notifica_a_los_estudiantes(): void
    {
        Notification::fake();
        $escenario = $this->escenario();

        $escenario['modulo']->update(['semana' => 1]);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('agregarLeccion', 'actividades', 1)
            ->set('titulo', 'Introducción')
            ->set('descripcion', 'Lectura de bienvenida al curso.')
            ->set('estado', 'publicado')
            ->call('guardarLeccion')
            ->assertHasNoErrors();

        Notification::assertSentTo($escenario['estudiante'], NuevoContenidoPublicado::class);
    }

    public function test_un_modulo_con_entregas_no_se_puede_eliminar(): void
    {
        $escenario = $this->escenario();
        $escenario['tarea']->entregas()->create(['estudiante_id' => $escenario['estudiante']->id, 'estado' => 'entregado', 'respuesta_texto' => 'x']);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('eliminarModulo', $escenario['modulo']->id);

        $this->assertNotNull($escenario['modulo']->fresh());
    }

    // CU-03 / RF-66
    public function test_marcar_una_leccion_como_completada_suma_al_avance(): void
    {
        $escenario = $this->escenario();
        $leccion = $escenario['modulo']->lecciones()->create(['titulo' => 'Lección', 'estado' => 'publicado', 'orden' => 1]);
        $servicio = app(ProgresoAcademicoService::class);
        $antes = $servicio->avance($escenario['estudiante'], $escenario['curso']);

        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('alternarLeccionCompletada', $leccion->id);

        $this->assertGreaterThan($antes, $servicio->avance($escenario['estudiante'], $escenario['curso']));
    }

    // RF-68: el panel del estudiante muestra una próxima entrega futura y solo cursos en marcha.
    public function test_el_panel_del_estudiante_muestra_la_proxima_entrega_futura_y_no_cursos_archivados(): void
    {
        $escenario = $this->escenario();
        $escenario['tarea']->update(['fecha_entrega' => now()->subDays(3)]);
        $futura = Tarea::factory()->create(['curso_id' => $escenario['curso']->id, 'modulo_id' => $escenario['modulo']->id, 'fecha_entrega' => now()->addDays(4)]);
        $archivado = Curso::factory()->archivado()->create(['profesor_id' => $escenario['profesor']->id]);
        $this->matricular($escenario['estudiante'], $archivado);

        $resumen = app(ProgresoAcademicoService::class)->resumenDashboardEstudiante($escenario['estudiante']);

        $this->assertTrue($resumen['proxima_entrega']->equalTo($futura->fecha_entrega));
        $this->assertSame([$escenario['curso']->id], $resumen['cursos']->pluck('curso.id')->all());
        // Tarea vencida + tarea futura + el quiz abierto del escenario.
        $this->assertSame(3, $resumen['total_pendientes']);
    }

    // RF-89: bajo rendimiento durante el periodo = rendimiento bajo la mínima o 2+ tareas vencidas sin entregar.
    public function test_el_bajo_rendimiento_no_marca_a_quien_va_bien_aunque_falte_contenido_futuro(): void
    {
        $escenario = $this->escenario();
        $rubros = $this->esquemaValido($escenario['curso'])->rubros;
        NotaRubro::create(['rubro_id' => $rubros[0]->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 18]);
        $atrasado = $this->usuarioConRol(Rol::Estudiante->value);
        $this->matricular($atrasado, $escenario['curso']);
        NotaRubro::create(['rubro_id' => $rubros[0]->id, 'estudiante_id' => $atrasado->id, 'nota' => 16]);
        foreach (range(1, 2) as $i) {
            Tarea::factory()->create(['curso_id' => $escenario['curso']->id, 'modulo_id' => $escenario['modulo']->id, 'fecha_entrega' => now()->subDays($i)]);
        }
        Entrega::create(['tarea_id' => Tarea::where('fecha_entrega', '<', now())->first()->id, 'estudiante_id' => $escenario['estudiante']->id, 'estado' => 'entregado', 'respuesta_texto' => 'x']);

        $bajo = app(ProgresoAcademicoService::class)->resumenCurso($escenario['curso'])['bajo_rendimiento']->pluck('estudiante.id')->all();

        // El estudiante va bien (18) y solo le falta una tarea vencida: no se marca aunque su avance total sea bajo.
        $this->assertNotContains($escenario['estudiante']->id, $bajo);
        // El otro tiene buena nota pero dos tareas vencidas sin entregar.
        $this->assertContains($atrasado->id, $bajo);
    }

    // RF-67: condiciones de completitud definidas por el Profesor.
    public function test_una_leccion_configurada_al_abrir_se_completa_al_abrir_su_material(): void
    {
        $escenario = $this->escenario();
        $leccion = $escenario['modulo']->lecciones()->create([
            'titulo' => 'Lectura', 'estado' => 'publicado', 'orden' => 1,
            'material_url' => 'https://ejemplo.edu.pe/lectura.pdf', 'condicion_completitud' => 'abrir_recurso',
        ]);

        // No se marca a mano.
        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('alternarLeccionCompletada', $leccion->id);
        $this->assertFalse($leccion->completadaPor($escenario['estudiante']));

        $this->actingAs($escenario['estudiante'])
            ->get(route('cursos.lecciones.recurso', [$escenario['curso'], $leccion, 'material']))
            ->assertRedirect('https://ejemplo.edu.pe/lectura.pdf');

        $this->assertTrue($leccion->completadaPor($escenario['estudiante']));
    }

    public function test_el_recurso_de_una_leccion_no_se_abre_desde_otro_curso_ni_sin_matricula(): void
    {
        $escenario = $this->escenario();
        $leccion = $escenario['modulo']->lecciones()->create([
            'titulo' => 'Lectura', 'estado' => 'publicado', 'orden' => 1, 'material_url' => 'https://ejemplo.edu.pe/a.pdf',
        ]);
        $otroCurso = Curso::factory()->create(['profesor_id' => $escenario['profesor']->id]);
        $this->matricular($escenario['estudiante'], $otroCurso);
        $ajeno = $this->usuarioConRol(Rol::Estudiante->value);

        $this->actingAs($escenario['estudiante'])
            ->get(route('cursos.lecciones.recurso', [$otroCurso, $leccion, 'material']))->assertNotFound();
        $this->actingAs($ajeno)
            ->get(route('cursos.lecciones.recurso', [$escenario['curso'], $leccion, 'material']))->assertForbidden();
    }

    public function test_un_modulo_con_condicion_de_actividades_exige_entregar_sus_tareas(): void
    {
        $escenario = $this->escenario();
        $escenario['modulo']->update(['condicion_completitud' => 'actividades']);
        $escenario['quiz']->delete();
        $leccion = $escenario['modulo']->lecciones()->create(['titulo' => 'Lección', 'estado' => 'publicado', 'orden' => 1]);
        $servicio = app(ProgresoAcademicoService::class);

        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('alternarLeccionCompletada', $leccion->id);

        $estado = $servicio->estadoModulos($escenario['estudiante'], $escenario['curso'])[$escenario['modulo']->id];
        $this->assertFalse($estado['completado']);
        $this->assertSame([1, 2], [$estado['hechos'], $estado['total']]);

        Entrega::create(['tarea_id' => $escenario['tarea']->id, 'estudiante_id' => $escenario['estudiante']->id, 'estado' => 'entregado', 'respuesta_texto' => 'Listo']);

        $this->assertTrue($servicio->estadoModulos($escenario['estudiante'], $escenario['curso'])[$escenario['modulo']->id]['completado']);
    }

    public function test_el_profesor_define_la_condicion_de_completitud_del_modulo_y_la_leccion(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('abrirEditarModulo', $escenario['modulo']->id)
            ->set('moduloCondicion', 'aprobacion')
            ->call('guardarModulo')
            ->assertHasNoErrors();

        $this->assertSame('aprobacion', $escenario['modulo']->fresh()->condicion_completitud);

        $escenario['modulo']->update(['semana' => 1]);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('agregarLeccion', 'actividades', 1)
            ->set('titulo', 'Sin recurso')
            ->set('estado', 'publicado')
            ->set('condicion_completitud', 'abrir_recurso')
            ->call('guardarLeccion')
            ->assertHasErrors('condicion_completitud');
    }

    // CU-04 / RF-42, RF-49
    public function test_el_estudiante_entrega_texto_y_enlace_sin_archivo(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['estudiante'])->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set('respuestaTexto', 'Mi respuesta escrita')
            ->set('enlace', 'https://docs.example.com/trabajo')
            ->call('entregar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entregas', ['estudiante_id' => $escenario['estudiante']->id, 'respuesta_texto' => 'Mi respuesta escrita', 'archivo_url' => null]);
    }

    public function test_una_entrega_vacia_es_rechazada(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['estudiante'])->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->call('entregar')
            ->assertHasErrors('archivo');
    }

    public function test_una_entrega_devuelta_se_puede_volver_a_entregar(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $entrega = $escenario['tarea']->entregas()->create(['estudiante_id' => $escenario['estudiante']->id, 'estado' => 'entregado', 'respuesta_texto' => 'v1']);

        Livewire::actingAs($escenario['profesor'])->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set("feedbacks.{$entrega->id}", 'Falta la conclusión')
            ->call('devolver', $entrega->id)
            ->assertHasNoErrors();

        Notification::assertSentTo($escenario['estudiante'], EntregaDevuelta::class);

        Livewire::actingAs($escenario['estudiante'])->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set('respuestaTexto', 'v2 con conclusión')
            ->call('entregar')
            ->assertHasNoErrors();

        $this->assertSame('v2 con conclusión', $entrega->fresh()->respuesta_texto);
        $this->assertSame(2, $entrega->fresh()->intentos_usados);
    }

    public function test_sin_intentos_disponibles_no_se_puede_reentregar(): void
    {
        $escenario = $this->escenario();
        $escenario['tarea']->entregas()->create(['estudiante_id' => $escenario['estudiante']->id, 'estado' => 'entregado', 'respuesta_texto' => 'v1', 'intentos_usados' => 1]);

        Livewire::actingAs($escenario['estudiante'])->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set('respuestaTexto', 'otra')
            ->call('entregar')
            ->assertHasErrors('archivo');
    }

    public function test_recalificar_una_entrega_queda_en_la_auditoria(): void
    {
        $escenario = $this->escenario();
        $entrega = $escenario['tarea']->entregas()->create(['estudiante_id' => $escenario['estudiante']->id, 'estado' => 'calificado', 'nota' => 12, 'respuesta_texto' => 'x']);

        Livewire::actingAs($escenario['profesor'])->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set("notas.{$entrega->id}", '16')
            ->call('calificar', $entrega->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'cambio_nota', 'entidad' => 'entrega_tarea', 'valor_anterior' => '12', 'valor_nuevo' => '16']);
    }

    // CU-05 / RF-45, RF-46
    public function test_el_quiz_respeta_la_cantidad_de_intentos(): void
    {
        $escenario = $this->escenario();
        $escenario['quiz']->update(['configuracion' => ['intentos' => 2]]);

        // Primer intento: el formulario se abre al montar el componente.
        Livewire::actingAs($escenario['estudiante'])->test(Resolver::class, ['quiz' => $escenario['quiz']->fresh()])
            ->set('respuestas', [0, 1])
            ->call('enviar')
            ->assertHasNoErrors();

        // Segundo intento: se inicia con "Nuevo intento".
        Livewire::actingAs($escenario['estudiante'])->test(Resolver::class, ['quiz' => $escenario['quiz']->fresh()])
            ->call('iniciarIntento')
            ->set('respuestas', [0, 1])
            ->call('enviar')
            ->assertHasNoErrors();

        $this->assertSame(2, ResultadoQuiz::count());

        Livewire::actingAs($escenario['estudiante'])->test(Resolver::class, ['quiz' => $escenario['quiz']->fresh()])
            ->call('iniciarIntento')
            ->assertForbidden();
    }

    public function test_un_intento_fuera_del_tiempo_limite_no_se_registra(): void
    {
        $escenario = $this->escenario();
        $escenario['quiz']->update(['configuracion' => ['tiempo_limite_min' => 5]]);

        $componente = Livewire::actingAs($escenario['estudiante'])->test(Resolver::class, ['quiz' => $escenario['quiz']->fresh()]);
        $this->travel(10)->minutes();

        $componente->set('respuestas', [0, 1])->call('enviar')->assertHasErrors('respuestas');
        $this->assertSame(0, ResultadoQuiz::count());
    }

    public function test_un_quiz_vencido_no_se_puede_rendir(): void
    {
        $escenario = $this->escenario();
        $escenario['quiz']->update(['fecha_limite' => now()->subHour()]);

        $this->assertFalse(Gate::forUser($escenario['estudiante'])->allows('responder', $escenario['quiz']->fresh()));
    }

    // CU-14 / RF-50 a RF-53
    public function test_corregir_una_pregunta_y_recalcular_actualiza_notas_audita_y_notifica(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $quiz = $escenario['quiz'];
        // Respuesta "incorrecta" según la clave original (correcta = 0 en la pregunta 1).
        ResultadoQuiz::create(['quiz_id' => $quiz->id, 'estudiante_id' => $escenario['estudiante']->id, 'intento' => 1, 'respuestas' => [2, 1], 'puntaje' => 10]);

        $formulario = Livewire::actingAs($escenario['profesor'])->test(QuizFormulario::class, ['curso' => $escenario['curso'], 'quiz' => $quiz])
            ->set('preguntas.0.respuesta_correcta', 2)
            ->call('guardar')
            ->assertSet('mostrarModalRecalculo', true)
            ->assertSee('1 estudiante(s) ya rindieron');

        $formulario->call('confirmarGuardado', true);

        $this->assertSame(20.0, (float) ResultadoQuiz::first()->puntaje);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'cambio_nota', 'entidad' => 'resultado_quiz', 'valor_anterior' => '10', 'valor_nuevo' => '20']);
        Notification::assertSentTo($escenario['estudiante'], NotaQuizModificada::class);
    }

    // RF-84: cambio de fecha de un quiz ya publicado.
    public function test_cambiar_la_fecha_limite_de_un_quiz_publicado_avisa_a_los_estudiantes(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $quiz = $escenario['quiz'];
        $quiz->update(['fecha_limite' => now()->addDays(3)]);

        Livewire::actingAs($escenario['profesor'])->test(QuizFormulario::class, ['curso' => $escenario['curso'], 'quiz' => $quiz])
            ->set('fecha_limite', now()->addDays(7)->format('Y-m-d\TH:i'))
            ->call('guardar')
            ->assertHasNoErrors();

        Notification::assertSentTo($escenario['estudiante'], CambioFechaQuiz::class);
    }

    public function test_guardar_sin_recalcular_mantiene_las_notas(): void
    {
        $escenario = $this->escenario();
        ResultadoQuiz::create(['quiz_id' => $escenario['quiz']->id, 'estudiante_id' => $escenario['estudiante']->id, 'intento' => 1, 'respuestas' => [2, 1], 'puntaje' => 10]);

        Livewire::actingAs($escenario['profesor'])->test(QuizFormulario::class, ['curso' => $escenario['curso'], 'quiz' => $escenario['quiz']])
            ->set('preguntas.0.respuesta_correcta', 2)
            ->call('guardar')
            ->call('confirmarGuardado', false);

        $this->assertSame(10.0, (float) ResultadoQuiz::first()->puntaje);
        $this->assertSame(2, (int) Quiz::first()->preguntas[0]['respuesta_correcta']);
    }

    public function test_con_intentos_no_se_pueden_agregar_preguntas(): void
    {
        $escenario = $this->escenario();
        ResultadoQuiz::create(['quiz_id' => $escenario['quiz']->id, 'estudiante_id' => $escenario['estudiante']->id, 'intento' => 1, 'respuestas' => [0, 1], 'puntaje' => 20]);

        Livewire::actingAs($escenario['profesor'])->test(QuizFormulario::class, ['curso' => $escenario['curso'], 'quiz' => $escenario['quiz']])
            ->call('agregarPregunta')
            ->assertForbidden();
    }

    // CU-17 / RF-70, RF-71
    public function test_el_profesor_pasa_lista_y_el_estudiante_ve_su_porcentaje(): void
    {
        $escenario = $this->escenario();
        $sesion = $escenario['curso']->sesionesClase()->create(['titulo' => 'Clase 1', 'fecha' => now()->subHour(), 'numero_sesion' => 1]);
        $escenario['curso']->sesionesClase()->create(['titulo' => 'Clase 2', 'fecha' => now()->subMinutes(30), 'numero_sesion' => 2]);

        Livewire::actingAs($escenario['profesor'])->test(AsistenciaIndex::class, ['curso' => $escenario['curso']])
            ->call('abrirPaseLista', $sesion->id)
            ->set("marcas.{$escenario['estudiante']->id}", 'presente')
            ->call('guardarAsistencia')
            ->assertHasNoErrors();

        $this->actingAs($escenario['estudiante'])->get(route('cursos.mi-asistencia', $escenario['curso']))
            ->assertOk()
            ->assertSee('50.0%');
    }

    public function test_un_curso_sin_asistencia_no_permite_registrarla(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['usa_asistencia' => false]);

        Livewire::actingAs($escenario['profesor'])->test(AsistenciaIndex::class, ['curso' => $escenario['curso']])
            ->set('sesionTitulo', 'Clase')
            ->call('crearSesion')
            ->assertForbidden();
    }

    // RF-73: el Profesor decide si su curso usa asistencia; el Supervisor solo consulta.
    public function test_el_profesor_omite_y_reactiva_la_asistencia_y_el_supervisor_solo_consulta(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['profesor'])->test(AsistenciaIndex::class, ['curso' => $escenario['curso']])
            ->call('alternarUsoAsistencia');
        $this->assertFalse($escenario['curso']->fresh()->usa_asistencia);

        Livewire::actingAs($escenario['profesor'])->test(AsistenciaIndex::class, ['curso' => $escenario['curso']->fresh()])
            ->call('alternarUsoAsistencia');
        $this->assertTrue($escenario['curso']->fresh()->usa_asistencia);

        Livewire::actingAs($escenario['supervisor'])->test(AsistenciaIndex::class, ['curso' => $escenario['curso']->fresh()])
            ->assertOk()
            ->call('alternarUsoAsistencia')
            ->assertForbidden();

        Livewire::actingAs($escenario['supervisor'])->test(AsistenciaIndex::class, ['curso' => $escenario['curso']->fresh()])
            ->set('sesionTitulo', 'Clase')
            ->call('crearSesion')
            ->assertForbidden();
    }

    // CU-16 / RF-74, RF-75
    public function test_el_profesor_crea_renombra_y_asigna_grupos_solo_con_matriculados(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->usuarioConRol(Rol::Estudiante->value);

        $componente = Livewire::actingAs($escenario['profesor'])->test(GruposIndex::class, ['curso' => $escenario['curso']])
            ->set('nuevoGrupoNombre', 'Equipo A')->call('crearGrupo');
        $grupo = $escenario['curso']->grupos()->firstOrFail();

        $componente->call('empezarRenombrar', $grupo->id)->set('nombreRenombrar', 'Equipo Alfa')->call('renombrar');
        $componente->call('mover', [$escenario['estudiante']->id], $grupo->id);

        $this->assertSame('Equipo Alfa', $grupo->fresh()->nombre);
        $this->assertTrue($grupo->estudiantes()->whereKey($escenario['estudiante']->id)->exists());

        $componente->call('mover', [$ajeno->id], $grupo->id)->assertForbidden();
    }

    // CU-08 / RF-80 a RF-83
    public function test_el_estudiante_escribe_al_profesor_de_su_curso(): void
    {
        Notification::fake();
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['estudiante'])->test(Bandeja::class, ['curso' => $escenario['curso']])
            ->set('destinatarioId', $escenario['profesor']->id)
            ->set('asunto', 'Consulta')
            ->set('cuerpo', '¿Cuándo es el parcial?')
            ->call('enviar')
            ->assertHasNoErrors();

        $this->assertSame(1, Mensaje::where('destinatario_id', $escenario['profesor']->id)->count());
        Notification::assertSentTo($escenario['profesor'], NuevoMensaje::class);
    }

    public function test_un_estudiante_no_envia_avisos_a_todo_el_curso(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['estudiante'])->test(Bandeja::class, ['curso' => $escenario['curso']])
            ->set('enviarATodos', true)
            ->set('asunto', 'Aviso')
            ->set('cuerpo', 'Mensaje a todos')
            ->call('enviar')
            ->assertForbidden();
    }

    // CU-22 / RF-87
    public function test_un_comunicado_programado_se_publica_y_notifica_a_su_hora(): void
    {
        Notification::fake();
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['supervisor'])->test(Comunicados::class)
            ->set('titulo', 'Cierre de ciclo')
            ->set('contenido', 'Detalles del cierre')
            ->set('destinatario', 'estudiantes')
            ->set('publicarEn', now()->addHour()->format('Y-m-d\TH:i'))
            ->call('crearComunicado')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
        $this->assertSame(0, Comunicado::activos()->count());

        $this->travel(2)->hours();
        Artisan::call('comunicados:publicar');

        Notification::assertSentTo($escenario['estudiante'], NuevoComunicado::class);
        Notification::assertNotSentTo($escenario['profesor'], NuevoComunicado::class);
        $this->assertSame(1, Comunicado::activos()->count());
    }

    // RF-84 / RF-37: el contenido programado avisa al estudiante cuando se publica solo, una única vez.
    public function test_el_contenido_programado_notifica_al_publicarse_una_sola_vez(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $tarea = Tarea::factory()->create([
            'curso_id' => $escenario['curso']->id,
            'modulo_id' => $escenario['modulo']->id,
            'estado_contenido' => 'programado',
            'publicar_en' => now()->addHour(),
            'fecha_entrega' => now()->addWeek(),
        ]);
        $leccion = $escenario['modulo']->lecciones()->create([
            'titulo' => 'Lección programada', 'estado' => 'programado', 'fecha_publicacion' => now()->addHour(), 'orden' => 1,
        ]);

        Artisan::call('contenido:notificar-programado');
        Notification::assertNothingSent();

        $this->travel(2)->hours();
        Artisan::call('contenido:notificar-programado');
        Artisan::call('contenido:notificar-programado');

        Notification::assertSentToTimes($escenario['estudiante'], NuevaTareaAsignada::class, 1);
        Notification::assertSentToTimes($escenario['estudiante'], NuevoContenidoPublicado::class, 1);
        Notification::assertNotSentTo($escenario['profesor'], NuevaTareaAsignada::class);
        $this->assertNotNull($tarea->fresh()->aviso_programado_en);
        $this->assertNotNull($leccion->fresh()->aviso_programado_en);
    }

    public function test_el_contenido_programado_de_un_curso_en_borrador_no_avisa(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $escenario['curso']->update(['estado' => 'borrador']);
        Tarea::factory()->create([
            'curso_id' => $escenario['curso']->id,
            'modulo_id' => $escenario['modulo']->id,
            'estado_contenido' => 'programado',
            'publicar_en' => now()->subMinute(),
        ]);

        Artisan::call('contenido:notificar-programado');

        Notification::assertNothingSent();
    }

    // CU-18 / RF-11 a RF-13
    public function test_la_importacion_crea_las_filas_validas_y_reporta_los_errores(): void
    {
        Mail::fake();
        $this->crearRoles();
        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);
        User::factory()->create(['email' => 'existe@x.pe']);

        $csv = "nombres;apellidos;dni;correo;rol\nAna;García;45678123;ana@x.pe;estudiante\nLuis;Pérez;45678124;correo-malo;estudiante\n;;45678125;vacio@x.pe;estudiante\nEva;Ruiz;45678126;existe@x.pe;profesor\nJuan;Ríos;45678127;juan@x.pe;supervisor\nAna;Copia;45678128;ana@x.pe;estudiante\n";

        $componente = Livewire::actingAs($supervisor)->test(ImportarCSV::class)
            ->set('archivo', UploadedFile::fake()->createWithContent('usuarios.csv', $csv))
            ->call('importar');

        $this->assertCount(1, $componente->get('creados'));
        $this->assertCount(1, $componente->get('omitidos'));
        $this->assertCount(4, $componente->get('errores'));
        $this->assertTrue(User::where('email', 'ana@x.pe')->firstOrFail()->primer_login);
        $this->assertFalse(User::where('email', 'juan@x.pe')->exists());
    }

    // RF-11: importación directa desde un libro de Excel
    public function test_la_importacion_acepta_un_libro_de_excel(): void
    {
        Mail::fake();
        $this->crearRoles();
        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);

        $libro = new LibroXlsx;
        $libro->hoja('Usuarios')
            ->encabezados(['Nombres', 'Apellidos', 'DNI', 'Correo', 'Rol'])
            ->fila(['Ana', 'García', '45678123', 'ana@x.pe', 'estudiante'])
            ->espacio()
            ->fila(['Luis', 'Pérez', '45678124', 'luis@x.pe', 'profesor'])
            ->fila(['Sin', 'Correo', '45678125', '', 'estudiante']);

        $componente = Livewire::actingAs($supervisor)->test(ImportarCSV::class)
            ->set('archivo', UploadedFile::fake()->createWithContent('usuarios.xlsx', $libro->contenido()))
            ->call('importar')
            ->assertHasNoErrors();

        $this->assertCount(2, $componente->get('creados'));
        $this->assertCount(1, $componente->get('errores'));
        $this->assertTrue(User::where('email', 'luis@x.pe')->firstOrFail()->hasRole(Rol::Profesor->value));
    }

    // RF-12: cada fila se valida con las mismas reglas del alta individual (nombres, DNI).
    public function test_la_importacion_informa_el_motivo_de_cada_fila_con_dni_o_nombre_invalido(): void
    {
        Mail::fake();
        $this->crearRoles();
        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);
        User::factory()->create(['dni' => '45678999']);

        $csv = "nombres,apellidos,dni,correo\n"
            ."Ana,García,45678123,ana@x.pe\n"
            ."Luis2,Pérez,45678124,luis@x.pe\n"
            ."Eva,Ruiz,7123456,eva@x.pe\n"
            ."Rosa,Díaz,45678123,rosa@x.pe\n"
            ."Juan,Ríos,45678999,juan@x.pe\n";

        $componente = Livewire::actingAs($supervisor)->test(ImportarCSV::class)
            ->set('archivo', UploadedFile::fake()->createWithContent('usuarios.csv', $csv))
            ->call('importar');

        $this->assertSame(['ana@x.pe'], array_column($componente->get('creados'), 'correo'));
        $this->assertSame([
            3 => "Los nombres solo pueden tener letras y espacios (también se permite guion y apóstrofe, como en Pérez-Salinas u O'Brien).",
            4 => 'El DNI debe tener exactamente 8 dígitos (solo números). Si el DNI empieza con 0, guarda la columna DNI como texto en Excel.',
            5 => 'DNI duplicado dentro del archivo (ya aparece en la línea 2).',
            6 => 'Ya existe otra cuenta con este DNI.',
        ], array_column($componente->get('errores'), 'error', 'linea'));
        $this->assertFalse(User::whereIn('email', ['luis@x.pe', 'eva@x.pe', 'rosa@x.pe', 'juan@x.pe'])->exists());
    }

    public function test_la_importacion_exige_las_columnas_de_nombres_apellidos_dni_y_correo(): void
    {
        $this->crearRoles();
        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);

        $componente = Livewire::actingAs($supervisor)->test(ImportarCSV::class)
            ->set('archivo', UploadedFile::fake()->createWithContent('usuarios.csv', "nombre,correo\nAna García,ana@x.pe\n"))
            ->call('importar');

        $this->assertSame(
            'Al archivo le faltan las columnas: nombres, apellidos, dni. Revisa el formato de ejemplo.',
            $componente->errors()->first('archivo'),
        );
        $this->assertDatabaseMissing('users', ['email' => 'ana@x.pe']);
    }

    public function test_un_excel_danado_muestra_un_error_y_no_crea_cuentas(): void
    {
        $this->crearRoles();
        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);

        Livewire::actingAs($supervisor)->test(ImportarCSV::class)
            ->set('archivo', UploadedFile::fake()->createWithContent('usuarios.xlsx', 'esto no es un zip'))
            ->call('importar')
            ->assertHasErrors('archivo');

        $this->assertSame(1, User::count());
    }

    // CU-09 / RF-78
    public function test_el_calendario_del_estudiante_incluye_tareas_quizzes_sesiones_y_evaluaciones(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['fecha_inicio' => now()->startOfMonth()]);
        $this->esquemaValido($escenario['curso']);
        $escenario['quiz']->update(['fecha_limite' => now()->addDays(3)]);
        $escenario['curso']->sesionesClase()->create(['titulo' => 'Clase inaugural', 'fecha' => now()->addDay(), 'numero_sesion' => 1]);

        $eventos = Livewire::actingAs($escenario['estudiante'])->test(Calendario::class)->instance()->eventos;
        $tipos = collect($eventos)->pluck('extendedProps.tipo')->unique()->sort()->values()->all();

        $this->assertSame(['evaluacion', 'quiz', 'sesion', 'tarea'], $tipos);
    }
}
