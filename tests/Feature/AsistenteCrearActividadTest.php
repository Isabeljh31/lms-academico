<?php

namespace Tests\Feature;

use App\Enums\ModoCalculo;
use App\Livewire\Cursos\Actividades;
use App\Livewire\Quizzes\Formulario as FormularioQuiz;
use App\Livewire\Tareas\Formulario as FormularioTarea;
use App\Models\Curso;
use App\Models\EsquemaEvaluacion;
use App\Models\Grupo;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Fase 1: asistente «¿Qué quieres crear?» de Actividades. Cada tipo abre el
 * formulario existente con solo lo necesario y, si es calificado, lo vincula
 * a la evaluación sugerida por semana (rubro_tarea / rubro_quiz).
 * El esquema de prueba tiene Parcial (semana 4, 40 %) y Final (semana 8, 60 %).
 */
class AsistenteCrearActividadTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_el_asistente_ofrece_los_cuatro_tipos_y_el_formulario_completo(): void
    {
        $e = $this->escenario();

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->assertSee('¿Qué quieres crear?')
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSeeInOrder(['Práctica calificada', 'Examen o quiz calificado', 'Quiz de práctica', 'Entrega sin nota'])
            ->assertSee(e(route('cursos.tareas.crear', [$e['curso'], 'desde' => 'actividades', 'tipo' => 'practica'])), false)
            ->assertSee(e(route('cursos.quizzes.crear', [$e['curso'], 'desde' => 'actividades', 'tipo' => 'examen'])), false)
            ->assertSee(e(route('cursos.quizzes.crear', [$e['curso'], 'desde' => 'actividades', 'tipo' => 'practica'])), false)
            ->assertSee(e(route('cursos.tareas.crear', [$e['curso'], 'desde' => 'actividades', 'tipo' => 'entrega'])), false)
            ->assertSee(e(route('cursos.tareas.crear', [$e['curso'], 'desde' => 'actividades'])), false);
    }

    public function test_la_practica_se_sugiere_por_semana_y_queda_vinculada_al_guardar(): void
    {
        $e = $this->escenario();
        $esquema = $this->esquemaValido($e['curso']);
        [$parcial, $final] = [$esquema->rubros[0], $esquema->rubros[1]];

        $formulario = $this->formularioTarea($e, 'practica')
            ->assertSee('Nueva práctica calificada')
            ->assertSee('¿A qué evaluación aporta?')
            ->assertDontSee('Peso en el resumen del alumno')
            ->set('modulo_id', $this->semana($e['curso'], 3)->id)
            ->assertSet('evaluacionId', $parcial->id)
            ->assertSee('La sugerimos porque es la primera evaluación desde la semana 3.')
            ->set('modulo_id', $this->semana($e['curso'], 6)->id)
            ->assertSet('evaluacionId', $final->id);

        $this->completarTarea($formulario, 'Práctica 1')->call('guardar')->assertHasNoErrors();

        $tarea = Tarea::where('titulo', 'Práctica 1')->sole();
        $this->assertDatabaseHas('rubro_tarea', ['rubro_id' => $final->id, 'tarea_id' => $tarea->id, 'peso' => 100]);
        $this->assertSame(ModoCalculo::Promedio, $final->fresh()->modo_calculo);
        $this->assertSame('1.00', $tarea->peso_nota);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'modificacion_esquema', 'ejecutado_por' => $e['profesor']->id]);
        $this->assertStringContainsString('Cuenta para Final.', session('estado'));
    }

    public function test_una_semana_posterior_a_la_evaluacion_avisa_sin_bloquear(): void
    {
        $e = $this->escenario();
        $parcial = $this->esquemaValido($e['curso'])->rubros[0];

        $formulario = $this->formularioTarea($e, 'practica')
            ->set('modulo_id', $this->semana($e['curso'], 10)->id)
            ->set('evaluacionId', $parcial->id)
            ->assertSee('Esta actividad es de la semana 10 y Parcial es de la semana 4. Puedes asignarla igual');

        // Elegida a mano, cambiar la semana ya no la reemplaza por la sugerida.
        $formulario->set('modulo_id', $this->semana($e['curso'], 2)->id)->assertSet('evaluacionId', $parcial->id)
            ->set('modulo_id', $this->semana($e['curso'], 10)->id)->assertSet('evaluacionId', $parcial->id);

        $this->completarTarea($formulario, 'Práctica tardía')->call('guardar')->assertHasNoErrors();
        $this->assertDatabaseHas('rubro_tarea', ['rubro_id' => $parcial->id, 'tarea_id' => Tarea::where('titulo', 'Práctica tardía')->value('id')]);
    }

    public function test_la_practica_es_grupal_por_defecto_si_el_curso_tiene_grupos(): void
    {
        $e = $this->escenario();
        $this->formularioTarea($e, 'practica')->assertSet('tipo_entrega', 'individual');

        Grupo::create(['curso_id' => $e['curso']->id, 'nombre' => 'G1']);

        $this->formularioTarea($e, 'practica')->assertSet('tipo_entrega', 'grupal')
            ->assertSee('Sube un integrante por grupo; la nota es para todo el grupo.');
        $this->formularioTarea($e, 'examen')->assertSet('tipo_entrega', 'individual');
    }

    public function test_un_quiz_calificado_en_una_evaluacion_vacia_vale_el_cien_por_ciento_como_examen(): void
    {
        $e = $this->escenario();
        $parcial = $this->esquemaValido($e['curso'])->rubros[0];

        Livewire::withQueryParams(['desde' => 'actividades', 'tipo' => 'examen'])->actingAs($e['profesor'])
            ->test(FormularioQuiz::class, ['curso' => $e['curso']])
            ->assertSee('Nuevo examen o quiz calificado')
            ->set('tema', 'Examen parcial')
            ->set('modulo_id', $this->semana($e['curso'], 4)->id)
            ->assertSet('evaluacionId', $parcial->id)
            ->set('preguntas', [['enunciado' => '¿2 + 2?', 'opciones' => ['3', '4'], 'respuesta_correcta' => 1]])
            ->call('guardar')->assertHasNoErrors();

        $quiz = Quiz::where('tema', 'Examen parcial')->sole();
        $this->assertDatabaseHas('rubro_quiz', ['rubro_id' => $parcial->id, 'quiz_id' => $quiz->id, 'peso' => 100]);
        $this->assertSame(ModoCalculo::Examen, $parcial->fresh()->modo_calculo);
    }

    public function test_una_actividad_mas_reparte_los_pesos_y_siempre_suman_cien(): void
    {
        $e = $this->escenario();
        $parcial = $this->esquemaValido($e['curso'])->rubros[0];
        $semana = $this->semana($e['curso'], 4);

        // Partes iguales: 100 → 50 / 50.
        $parcial->update(['modo_calculo' => ModoCalculo::Examen]);
        $parcial->quizzes()->attach($e['quiz']->id, ['peso' => 100]);
        $this->completarTarea($this->formularioTarea($e, 'practica')->set('modulo_id', $semana->id), 'P1')->call('guardar')->assertHasNoErrors();
        $this->assertSame(ModoCalculo::Promedio, $parcial->fresh()->modo_calculo);
        $this->assertEqualsCanonicalizing([50.0, 50.0], $this->pesos($parcial));

        // Pesos ajustados por el profesor (70 / 30): se conservan sus proporciones.
        $parcial->quizzes()->updateExistingPivot($e['quiz']->id, ['peso' => 70]);
        $parcial->tareas()->updateExistingPivot(Tarea::where('titulo', 'P1')->value('id'), ['peso' => 30]);
        $this->completarTarea($this->formularioTarea($e, 'practica')->set('modulo_id', $semana->id), 'P2')->call('guardar')->assertHasNoErrors();

        $pesos = $this->pesos($parcial);
        $this->assertEqualsWithDelta(100, array_sum($pesos), 0.001);
        $this->assertEqualsCanonicalizing([46.67, 20.0, 33.33], $pesos);
        $this->assertStringContainsString('Puedes cambiarlos con «Cambiar reparto»', session('estado'));
    }

    public function test_el_quiz_de_practica_y_la_entrega_sin_nota_no_cuentan_para_ninguna_evaluacion(): void
    {
        $e = $this->escenario();
        $this->esquemaValido($e['curso']);

        Livewire::withQueryParams(['desde' => 'actividades', 'tipo' => 'practica'])->actingAs($e['profesor'])
            ->test(FormularioQuiz::class, ['curso' => $e['curso']])
            ->assertSee('Nuevo quiz de práctica')
            ->assertSee('Este quiz no cuenta para la nota.')
            ->assertDontSee('¿A qué evaluación aporta?')
            ->set('tema', 'Repaso')->set('modulo_id', $e['modulo']->id)
            ->set('preguntas', [['enunciado' => '¿2 + 2?', 'opciones' => ['3', '4'], 'respuesta_correcta' => 1]])
            ->call('guardar')->assertHasNoErrors();

        $this->completarTarea($this->formularioTarea($e, 'entrega')
            ->assertSee('Nueva entrega sin nota')
            ->assertSee('No lleva nota ni cuenta para ninguna evaluación.')
            ->assertDontSee('¿A qué evaluación aporta?')
            ->set('modulo_id', $e['modulo']->id), 'Subir ppt')->call('guardar')->assertHasNoErrors();

        $this->assertDatabaseCount('rubro_quiz', 0);
        $this->assertDatabaseCount('rubro_tarea', 0);
        $this->assertTrue(Quiz::where('tema', 'Repaso')->exists() && Tarea::where('titulo', 'Subir ppt')->exists());
    }

    public function test_no_acepta_evaluaciones_manuales_ni_de_otro_curso(): void
    {
        $e = $this->escenario();
        [$parcial, $final] = $this->esquemaValido($e['curso'])->rubros->all();
        $final->update(['modo_calculo' => ModoCalculo::Manual]);
        $ajeno = EsquemaEvaluacion::create(['curso_id' => Curso::factory()->create()->id])
            ->rubros()->create(['nombre' => 'Ajena', 'semana' => 1, 'peso' => 100, 'orden' => 1]);

        $formulario = $this->formularioTarea($e, 'practica')
            ->set('modulo_id', $this->semana($e['curso'], 6)->id)
            ->assertSet('evaluacionId', $parcial->id)
            ->assertSee('No aparecen Final: su nota la escribes tú en el libro de calificaciones.');

        foreach ([$final->id, $ajeno->id] as $prohibida) {
            $this->completarTarea($formulario->set('evaluacionId', $prohibida), 'Intento')->call('guardar')->assertHasErrors('evaluacionId');
        }

        $this->assertDatabaseCount('rubro_tarea', 0);
        $this->assertSame(ModoCalculo::Manual, $final->fresh()->modo_calculo);
        $this->assertFalse(Tarea::where('titulo', 'Intento')->exists());
    }

    public function test_sin_esquema_se_puede_crear_y_asignar_despues(): void
    {
        $e = $this->escenario();

        $this->completarTarea($this->formularioTarea($e, 'practica')
            ->assertSee('El Supervisor todavía no definió las evaluaciones del curso.')
            ->set('modulo_id', $e['modulo']->id), 'Práctica libre')->call('guardar')->assertHasNoErrors();

        $this->assertDatabaseCount('rubro_tarea', 0);
        $this->assertStringContainsString('asígnala desde Actividades', session('estado'));
    }

    public function test_al_editar_o_sin_tipo_el_formulario_sigue_como_antes(): void
    {
        $e = $this->escenario();
        $this->esquemaValido($e['curso']);

        Livewire::withQueryParams(['tipo' => 'practica'])->actingAs($e['profesor'])
            ->test(FormularioTarea::class, ['curso' => $e['curso'], 'tarea' => $e['tarea']])
            ->assertSet('tipoActividad', '')->assertSee('Editar tarea')
            ->assertDontSee('¿A qué evaluación aporta?')->assertSee('Peso en el resumen del alumno')
            ->call('guardar')->assertHasNoErrors();
        Livewire::withQueryParams(['tipo' => 'otra-cosa'])->actingAs($e['profesor'])
            ->test(FormularioQuiz::class, ['curso' => $e['curso']])
            ->assertSet('tipoActividad', '')->assertSee('Nuevo quiz');

        $this->assertDatabaseCount('rubro_tarea', 0);
    }

    public function test_la_lista_dice_claramente_que_una_actividad_no_cuenta_para_la_nota(): void
    {
        $e = $this->escenario();
        $this->esquemaValido($e['curso']);

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->assertSee('No cuenta para la nota · Asignar')
            ->assertSee('No cuentan para la nota')
            ->assertDontSee('Sin evaluación');
    }

    /**
     * @param  array<string, mixed>  $e
     */
    private function formularioTarea(array $e, string $tipo): Testable
    {
        return Livewire::withQueryParams(['desde' => 'actividades', 'tipo' => $tipo])->actingAs($e['profesor'])
            ->test(FormularioTarea::class, ['curso' => $e['curso']]);
    }

    private function completarTarea(Testable $formulario, string $titulo): Testable
    {
        return $formulario->set('titulo', $titulo)->set('fecha_entrega', now()->addWeek()->format('Y-m-d\TH:i'));
    }

    private function semana(Curso $curso, int $numero): Modulo
    {
        return Modulo::firstOrCreate(['curso_id' => $curso->id, 'semana' => $numero], ['titulo' => "Semana {$numero}", 'orden' => $numero, 'materiales' => []]);
    }

    /** @return list<float> */
    private function pesos($rubro): array
    {
        $rubro->load(['tareas', 'quizzes']);

        return $rubro->actividadesVinculadas()->pluck('peso')->map(fn ($peso) => round($peso, 2))->all();
    }
}
