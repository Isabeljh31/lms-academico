<?php

namespace Tests\Feature;

use App\Enums\ModoCalculo;
use App\Livewire\Cursos\Actividades;
use App\Livewire\Cursos\BoletaEstudiante;
use App\Livewire\Cursos\EsquemaEvaluacion;
use App\Livewire\Cursos\LibroCalificaciones;
use App\Models\Entrega;
use App\Models\NotaRubro;
use App\Models\Quiz;
use App\Models\ResultadoQuiz;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Evaluacion\NotasDeEvaluacion;
use App\Services\Reportes\ProgresoAcademicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

class EvaluacionesAutomaticasTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_cuatro_quizzes_se_reparten_en_partes_iguales_y_aportan_su_promedio_al_cuarenta_por_ciento(): void
    {
        $e = $this->escenario();
        $esquema = $this->esquemaValido($e['curso']);
        $rubro = $esquema->rubros->first();
        $quizzes = collect([$e['quiz']])->concat(Quiz::factory()->count(3)->create(['curso_id' => $e['curso']->id, 'modulo_id' => $e['modulo']->id]));

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->call('configurarCalculo', $rubro->id)->assertSee('¿Cómo se obtiene la nota?')
            ->set('modoCalculo', 'promedio')->set('quizzesCalculo', $quizzes->pluck('id')->all())
            ->assertSet('pesosCalculo', $quizzes->mapWithKeys(fn ($quiz) => ['quiz-'.$quiz->id => '25'])->all())
            ->call('guardarCalculo')->assertHasNoErrors()->assertSet('mostrarCalculo', false);
        $this->assertSame(ModoCalculo::Promedio, $rubro->fresh()->modo_calculo);
        $this->assertDatabaseCount('rubro_quiz', 4);
        $this->assertDatabaseHas('rubro_quiz', ['quiz_id' => $e['quiz']->id, 'peso' => 25]);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'modificacion_esquema', 'ejecutado_por' => $e['profesor']->id]);

        foreach ([12, 16, 20, 16] as $i => $puntaje) {
            ResultadoQuiz::factory()->create(['quiz_id' => $quizzes[$i]->id, 'estudiante_id' => $e['estudiante']->id, 'puntaje' => $puntaje, 'intento' => 1]);
        }
        // Un intento inferior no reemplaza el mejor puntaje.
        ResultadoQuiz::factory()->create(['quiz_id' => $quizzes[0]->id, 'estudiante_id' => $e['estudiante']->id, 'puntaje' => 2, 'intento' => 2]);

        $calculadora = app(CalculadoraNotas::class);
        $this->assertSame(6.4, $calculadora->notaFinalDe($e['estudiante'], $e['curso']->fresh()));
        $this->assertSame(16.0, $calculadora->rendimientoDe($e['estudiante'], $e['curso']->fresh()));
        $resumen = app(ProgresoAcademicoService::class)->resumenRubros($e['estudiante'], $e['curso']->fresh());
        $this->assertSame(16.0, $resumen['filas']->first()['nota']);
        $this->assertSame('en_curso', $resumen['condicion']);
        Livewire::actingAs($e['profesor'])->test(LibroCalificaciones::class, ['curso' => $e['curso']])
            ->assertSee('16.0')->assertSee('6.4')->assertSee('Promedio de actividades');
        Livewire::actingAs($e['estudiante'])->test(BoletaEstudiante::class, ['curso' => $e['curso']])
            ->assertSee('16.0')->assertSee('6.4');

        NotaRubro::create(['rubro_id' => $esquema->rubros->last()->id, 'estudiante_id' => $e['estudiante']->id, 'nota' => 18, 'registrado_por' => $e['profesor']->id]);
        $resumenFinal = $calculadora->resumenDelCurso($e['curso']->fresh(), collect([$e['estudiante']->id]))[$e['estudiante']->id];
        $this->assertSame(17.2, $resumenFinal['nota_final']);
        $this->assertSame('aprobado', $resumenFinal['condicion']);
    }

    public function test_el_promedio_pondera_cada_actividad_por_su_peso(): void
    {
        $e = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->call('configurarCalculo', $rubro->id)
            ->set('tareasCalculo', [$e['tarea']->id])->set('quizzesCalculo', [$e['quiz']->id])
            ->set('pesosCalculo', ['tarea-'.$e['tarea']->id => '70', 'quiz-'.$e['quiz']->id => '30'])
            ->call('guardarCalculo')->assertHasNoErrors();
        Entrega::create(['tarea_id' => $e['tarea']->id, 'estudiante_id' => $e['estudiante']->id, 'estado' => 'calificado', 'nota' => 20]);
        ResultadoQuiz::factory()->create(['quiz_id' => $e['quiz']->id, 'estudiante_id' => $e['estudiante']->id, 'puntaje' => 10]);

        $nota = app(NotasDeEvaluacion::class)->resolver(collect([$rubro->fresh()]), collect([$e['estudiante']->id]))[$e['estudiante']->id][$rubro->id];

        $this->assertSame(17.0, $nota);
    }

    public function test_los_pesos_deben_ser_validos_y_sumar_cien(): void
    {
        $e = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();
        $tarea = 'tarea-'.$e['tarea']->id;
        $quiz = 'quiz-'.$e['quiz']->id;

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->call('configurarCalculo', $rubro->id)
            ->set('tareasCalculo', [$e['tarea']->id])->set('quizzesCalculo', [$e['quiz']->id])
            ->set('pesosCalculo', [$tarea => '30', $quiz => '50'])
            ->assertSee('Faltan 20 % para llegar a 100 %.')
            ->call('guardarCalculo')->assertHasErrors('pesosCalculo')
            ->set('pesosCalculo', [$tarea => '0', $quiz => '100'])
            ->call('guardarCalculo')->assertHasErrors("pesosCalculo.$tarea")
            ->set('pesosCalculo', [$tarea => 'abc', $quiz => '100'])
            ->call('guardarCalculo')->assertHasErrors("pesosCalculo.$tarea")
            ->call('repartirPesos')->assertSet('pesosCalculo', [$tarea => '50', $quiz => '50'])
            ->assertSee('Suma 100 %. Listo para guardar.');

        $this->assertNull($rubro->fresh()->modo_calculo);
        $this->assertDatabaseCount('rubro_tarea', 0);
    }

    public function test_el_examen_vale_el_cien_por_ciento_de_la_evaluacion(): void
    {
        $e = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();
        $componente = Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->call('configurarCalculo', $rubro->id)->set('modoCalculo', 'examen');

        $componente->call('guardarCalculo')->assertHasErrors('examenCalculo');
        $componente->set('examenCalculo', 'quiz-'.$e['quiz']->id)->call('guardarCalculo')->assertHasNoErrors();
        ResultadoQuiz::factory()->create(['quiz_id' => $e['quiz']->id, 'estudiante_id' => $e['estudiante']->id, 'puntaje' => 15]);

        $this->assertSame(ModoCalculo::Examen, $rubro->fresh()->modo_calculo);
        $this->assertDatabaseHas('rubro_quiz', ['rubro_id' => $rubro->id, 'quiz_id' => $e['quiz']->id, 'peso' => 100]);
        $this->assertSame(6.0, app(CalculadoraNotas::class)->notaFinalDe($e['estudiante'], $e['curso']->fresh()));
    }

    public function test_tareas_y_quizzes_se_recalculan_y_no_omiten_notas_pendientes_ni_ceros(): void
    {
        $e = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();
        $rubro->update(['modo_calculo' => ModoCalculo::Promedio]);
        $rubro->tareas()->attach($e['tarea'], ['peso' => 50]);
        $rubro->quizzes()->attach($e['quiz'], ['peso' => 50]);
        $resultado = ResultadoQuiz::factory()->create(['quiz_id' => $e['quiz']->id, 'estudiante_id' => $e['estudiante']->id, 'puntaje' => 20]);
        $resolver = fn () => app(NotasDeEvaluacion::class)->resolver(collect([$rubro->fresh()]), collect([$e['estudiante']->id]))[$e['estudiante']->id][$rubro->id];
        $this->assertNull($resolver());
        $entrega = Entrega::create(['tarea_id' => $e['tarea']->id, 'estudiante_id' => $e['estudiante']->id, 'estado' => 'entregado', 'nota' => 0]);
        $this->assertNull($resolver());
        $entrega->update(['estado' => 'calificado']);
        $this->assertSame(10.0, $resolver());
        $entrega->update(['nota' => 16]);
        $this->assertSame(18.0, $resolver());
        $resultado->update(['puntaje' => 12]);
        $this->assertSame(14.0, $resolver());
    }

    public function test_volver_a_nota_manual_recupera_la_nota_y_libera_las_actividades(): void
    {
        $e = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();
        NotaRubro::create(['rubro_id' => $rubro->id, 'estudiante_id' => $e['estudiante']->id, 'nota' => 13, 'registrado_por' => $e['profesor']->id]);
        $componente = Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']]);
        $componente->call('configurarCalculo', $rubro->id)->set('modoCalculo', 'promedio')
            ->set('quizzesCalculo', [$e['quiz']->id])->call('guardarCalculo')->assertHasNoErrors();
        $this->assertNull(app(CalculadoraNotas::class)->notaFinalDe($e['estudiante'], $e['curso']->fresh()));
        $componente->call('configurarCalculo', $rubro->id)->set('modoCalculo', 'manual')
            ->call('guardarCalculo')->assertHasNoErrors();
        $this->assertSame(5.2, app(CalculadoraNotas::class)->notaFinalDe($e['estudiante'], $e['curso']->fresh()));
        $this->assertSame('40.00', $rubro->fresh()->peso);
        $this->assertSame(ModoCalculo::Manual, $rubro->fresh()->modo_calculo);
        $this->assertDatabaseCount('rubro_quiz', 0);
    }

    public function test_no_admite_promedio_sin_actividades_ni_actividades_de_otro_curso(): void
    {
        $e = $this->escenario();
        $otro = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();
        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->call('configurarCalculo', $rubro->id)->set('modoCalculo', 'promedio')
            ->call('guardarCalculo')->assertHasErrors('actividadesCalculo')
            ->set('quizzesCalculo', [$otro['quiz']->id])->call('guardarCalculo')->assertHasErrors('quizzesCalculo.0')
            ->set('quizzesCalculo', [])->set('modoCalculo', 'examen')->set('examenCalculo', 'quiz-'.$otro['quiz']->id)
            ->call('guardarCalculo')->assertHasErrors('examenCalculo');
        $this->assertNull($rubro->fresh()->modo_calculo);
    }

    public function test_solo_el_profesor_del_curso_configura_y_no_puede_editar_una_nota_automatica(): void
    {
        $e = $this->escenario();
        $otro = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();
        foreach ([$e['supervisor'], $e['estudiante'], $otro['profesor']] as $usuario) {
            Livewire::actingAs($usuario)->test(Actividades::class, ['curso' => $e['curso']])->assertForbidden();
        }
        $rubro->update(['modo_calculo' => ModoCalculo::Promedio]);
        $rubro->quizzes()->attach($e['quiz'], ['peso' => 100]);
        Livewire::actingAs($e['profesor'])->test(LibroCalificaciones::class, ['curso' => $e['curso']])
            ->call('abrirModalNota', $rubro->id, $e['estudiante']->id)->assertForbidden();
        $this->assertFalse($e['quiz']->puedeEliminarse());
        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->call('confirmarEliminar', 'quiz', $e['quiz']->id)->assertForbidden();
        $e['curso']->update(['estado' => 'archivado']);
        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->call('configurarCalculo', $rubro->id)->assertForbidden();
    }

    public function test_no_se_puede_configurar_una_evaluacion_de_otro_curso(): void
    {
        $e = $this->escenario();
        $otro = $this->escenario();
        $rubro = $this->esquemaValido($otro['curso'])->rubros->first();
        $this->esquemaValido($e['curso']);
        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->call('configurarCalculo', $rubro->id)->assertNotFound();
    }

    public function test_una_actividad_cuenta_para_una_sola_evaluacion(): void
    {
        $e = $this->escenario();
        [$parcial, $final] = $this->esquemaValido($e['curso'])->rubros->all();
        $componente = Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']]);
        $componente->call('configurarCalculo', $parcial->id)
            ->set('quizzesCalculo', [$e['quiz']->id])->call('guardarCalculo')->assertHasNoErrors();

        $componente->call('configurarCalculo', $final->id)->assertSee('Ya cuenta para Parcial')
            ->set('quizzesCalculo', [$e['quiz']->id])->call('guardarCalculo')->assertHasErrors('actividadesCalculo')
            ->set('quizzesCalculo', [])->set('modoCalculo', 'examen')->set('examenCalculo', 'quiz-'.$e['quiz']->id)
            ->call('guardarCalculo')->assertHasErrors('actividadesCalculo');

        $this->assertDatabaseCount('rubro_quiz', 1);
        $this->assertDatabaseHas('rubro_quiz', ['rubro_id' => $parcial->id, 'quiz_id' => $e['quiz']->id]);
    }

    public function test_la_lista_muestra_a_que_evaluacion_pertenece_cada_actividad_y_asigna_las_libres(): void
    {
        $e = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();
        $rubro->update(['modo_calculo' => ModoCalculo::Promedio]);
        $rubro->quizzes()->attach($e['quiz'], ['peso' => 100]);

        $componente = Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->assertSee('Parcial · 100 %')->assertSee('Sin evaluación · Asignar')
            ->call('filtrar', 'sin_evaluacion')
            ->assertViewHas('actividades', fn ($actividades) => $actividades->pluck('id', 'tipo')->all() === ['tarea' => $e['tarea']->id]);

        $componente->call('configurarCalculo', $rubro->id, 'tarea-'.$e['tarea']->id)
            ->assertSet('tareasCalculo', [$e['tarea']->id])
            ->assertSet('pesosCalculo', ['tarea-'.$e['tarea']->id => '50', 'quiz-'.$e['quiz']->id => '50']);
    }

    public function test_el_esquema_no_elimina_evaluaciones_automaticas_sin_desactivarlas(): void
    {
        $e = $this->escenario();
        $esquema = $this->esquemaValido($e['curso']);
        $rubro = $esquema->rubros->first();
        $rubro->update(['modo_calculo' => ModoCalculo::Promedio]);
        $rubro->quizzes()->attach($e['quiz'], ['peso' => 100]);
        $componente = Livewire::actingAs($e['supervisor'])->test(EsquemaEvaluacion::class, ['curso' => $e['curso']]);
        $componente->call('quitarRubro', 0)->assertHasErrors('rubros.0.nombre');
        $restante = $esquema->rubros->last();
        $componente->set('rubros', [['id' => $restante->id, 'nombre' => $restante->nombre, 'semana' => 1, 'peso' => 100]])
            ->call('guardar')->assertHasErrors('rubros');
        $this->assertDatabaseHas('rubros_evaluacion', ['id' => $rubro->id]);
        $this->assertDatabaseCount('rubro_quiz', 1);
    }
}
