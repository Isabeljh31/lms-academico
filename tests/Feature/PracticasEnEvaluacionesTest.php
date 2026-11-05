<?php

namespace Tests\Feature;

use App\Enums\ModoCalculo;
use App\Livewire\Cursos\Actividades;
use App\Livewire\Cursos\EsquemaEvaluacion as EsquemaEvaluacionComponente;
use App\Livewire\Cursos\PracticasEvaluaciones;
use App\Livewire\Tareas\Formulario as FormularioTarea;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\EsquemaEvaluacion;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use App\Services\Evaluacion\NotasDeEvaluacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * El Supervisor fija cuánto valen las prácticas de campo dentro de las
 * evaluaciones (p. ej. 30 %) y el profesor reparte ese % en tramos: una
 * evaluación con todas las prácticas o varias con un tramo cada una. Cada
 * práctica vale partes iguales dentro de su tramo.
 */
class PracticasEnEvaluacionesTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_el_supervisor_define_el_peso_de_las_practicas_solo_si_el_curso_las_tiene(): void
    {
        $e = $this->escenario();
        $this->esquemaValido($e['curso']);

        Livewire::actingAs($e['supervisor'])->test(EsquemaEvaluacionComponente::class, ['curso' => $e['curso']])
            ->assertDontSee('Peso de las prácticas de campo');

        $e['curso']->update(['tiene_practicas_campo' => true, 'semanas_duracion' => 12]);

        Livewire::actingAs($e['supervisor'])->test(EsquemaEvaluacionComponente::class, ['curso' => $e['curso']->fresh()])
            ->assertSee('Peso de las prácticas de campo')
            ->call('guardar')
            ->assertHasErrors(['pesoPracticas' => 'required'])
            ->set('pesoPracticas', '30')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('30.00', $e['curso']->esquemaEvaluacion->fresh()->peso_practicas_campo);
    }

    public function test_el_profesor_reparte_las_practicas_en_tramos_entre_varias_evaluaciones(): void
    {
        [$e, $rubros] = $this->cursoConPracticas(12, 30);
        $otra = Tarea::factory()->create(['curso_id' => $e['curso']->id, 'modulo_id' => $e['modulo']->id]);
        $rubros[0]->tareas()->attach($otra->id, ['peso' => 100]);
        $practicas = collect(range(1, 12))->map(fn (int $n) => $this->practica($e['curso'], $n));

        $this->panel($e)
            ->set('tramos', $this->tramos($rubros, [[1, 4, '10'], [5, 8, '10'], [9, 12, '10']]))
            ->call('guardar')
            ->assertHasNoErrors();

        $rubros->each->refresh();
        $this->assertSame([1, 4, '10.00'], [$rubros[0]->practica_desde, $rubros[0]->practica_hasta, $rubros[0]->peso_practicas]);
        // Cada práctica del tramo: 10 % / 4 = 2,5 %; la otra actividad pasa de 100 a 90 %.
        $this->assertSame(2.5, (float) $rubros[0]->tareas()->find($practicas[0]->id)->pivot->peso);
        $this->assertSame(2.5, (float) $rubros[2]->tareas()->find($practicas[11]->id)->pivot->peso);
        $this->assertSame(90.0, (float) $rubros[0]->tareas()->find($otra->id)->pivot->peso);
        $this->assertSame('lista', $rubros[0]->load(['tareas', 'quizzes'])->estadoCalculo());
        $this->assertNull($rubros[1]->tareas()->find($practicas[0]->id));
    }

    public function test_el_profesor_puede_poner_todas_las_practicas_en_una_sola_evaluacion(): void
    {
        [$e, $rubros] = $this->cursoConPracticas(12, 30);
        $this->practica($e['curso'], 7);

        $this->panel($e)
            ->call('todasEn', $rubros[1]->id)
            ->assertSet('tramos.'.$rubros[1]->id, ['desde' => '1', 'hasta' => '12', 'peso' => '30'])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame([null, 1, null], $rubros->map(fn ($rubro) => $rubro->fresh()->practica_desde)->all());
        $this->assertSame(2.5, (float) $rubros[1]->tareas()->sole()->pivot->peso);
    }

    public function test_el_reparto_debe_cubrir_todas_las_practicas_sin_solaparse_y_sumar_el_total_del_supervisor(): void
    {
        [$e, $rubros] = $this->cursoConPracticas(12, 30);

        $this->panel($e)
            ->set('tramos', $this->tramos($rubros, [[1, 4, '10'], [5, 8, '10'], [9, 12, '5']]))
            ->call('guardar')
            ->assertHasErrors('tramos')
            ->set('tramos', $this->tramos($rubros, [[1, 4, '10'], [4, 8, '10'], [9, 12, '10']]))
            ->call('guardar')
            ->assertHasErrors('tramos.'.$rubros[1]->id)
            ->set('tramos', $this->tramos($rubros, [[1, 4, '15'], [5, 8, '15'], [null, null, '']]))
            ->call('guardar')
            ->assertHasErrors('tramos')
            ->set('tramos', $this->tramos($rubros, [[1, 13, '30'], [null, null, ''], [null, null, '']]))
            ->call('guardar')
            ->assertHasErrors('tramos.'.$rubros[0]->id);

        $this->assertNull($rubros[0]->fresh()->practica_desde);
    }

    public function test_la_nota_de_la_evaluacion_incluye_las_practicas_y_espera_a_que_esten_todas(): void
    {
        [$e, $rubros] = $this->cursoConPracticas(2, 20, cantidadRubros: 1);
        $examen = Tarea::factory()->create(['curso_id' => $e['curso']->id, 'modulo_id' => $e['modulo']->id]);
        $rubros[0]->tareas()->attach($examen->id, ['peso' => 100]);
        $primera = $this->practica($e['curso'], 1);

        $this->panel($e)->set('tramos', $this->tramos($rubros, [[1, 2, '20']]))->call('guardar')->assertHasNoErrors();

        $this->calificar($primera, $e, 20);
        $this->calificar($examen, $e, 10);
        // Falta preparar la práctica 2: la evaluación no tiene nota todavía.
        $this->assertNull($this->notaDe($rubros[0], $e));

        // Al crear la entrega de la práctica 2 se vincula sola a su evaluación.
        Livewire::withQueryParams(['practica' => 2])->actingAs($e['profesor'])
            ->test(FormularioTarea::class, ['curso' => $e['curso']])
            ->assertDontSee('¿A qué evaluación aporta?')
            ->set('fecha_entrega', now()->addWeek()->format('Y-m-d\TH:i'))
            ->call('guardar')
            ->assertHasNoErrors();
        $segunda = Tarea::where('practica', 2)->sole();
        $this->assertSame(10.0, (float) $rubros[0]->tareas()->find($segunda->id)->pivot->peso);

        $this->calificar($segunda, $e, 0);
        // Examen 10 × 80 % + práctica 1: 20 × 10 % + práctica 2: 0 × 10 % = 10.
        $this->assertSame(10.0, $this->notaDe($rubros[0], $e));
    }

    public function test_las_demas_actividades_de_la_evaluacion_suman_el_porcentaje_restante(): void
    {
        [$e, $rubros] = $this->cursoConPracticas(4, 30, cantidadRubros: 1);
        $practica = $this->practica($e['curso'], 1);
        $this->panel($e)->set('tramos', $this->tramos($rubros, [[1, 4, '30']]))->call('guardar')->assertHasNoErrors();

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->assertSee('Prácticas de campo 1–4')
            ->call('configurarCalculo', $rubros[0]->id)
            ->assertSet('tareasCalculo', [])
            ->set('tareasCalculo', [$e['tarea']->id])
            ->assertSet('pesosCalculo', ['tarea-'.$e['tarea']->id => '70'])
            ->set('pesosCalculo', ['tarea-'.$e['tarea']->id => '100'])
            ->call('guardarCalculo')
            ->assertHasErrors('pesosCalculo')
            ->set('pesosCalculo', ['tarea-'.$e['tarea']->id => '70'])
            ->call('guardarCalculo')
            ->assertHasNoErrors();

        $this->assertSame(70.0, (float) $rubros[0]->tareas()->find($e['tarea']->id)->pivot->peso);
        // La práctica conserva su vínculo y su peso.
        $this->assertSame(7.5, (float) $rubros[0]->tareas()->find($practica->id)->pivot->peso);

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->call('configurarCalculo', $rubros[0]->id)
            ->set('modoCalculo', ModoCalculo::Manual->value)
            ->call('guardarCalculo')
            ->assertHasErrors('modoCalculo');
    }

    /**
     * Curso con prácticas de campo (una por semana), el % del Supervisor y
     * evaluaciones en modo promedio.
     *
     * @return array{0: array<string, mixed>, 1: Collection<int, RubroEvaluacion>}
     */
    private function cursoConPracticas(int $semanas, float $pesoPracticas, int $cantidadRubros = 3): array
    {
        $e = $this->escenario();
        $e['curso']->update(['tiene_practicas_campo' => true, 'semanas_duracion' => $semanas]);
        $esquema = EsquemaEvaluacion::firstOrCreate(['curso_id' => $e['curso']->id], ['peso_practicas_campo' => $pesoPracticas]);
        $esquema->update(['peso_practicas_campo' => $pesoPracticas]);
        $pesos = $cantidadRubros === 1 ? [100] : [30, 30, 40];

        $rubros = collect(range(1, $cantidadRubros))->map(fn (int $n) => $esquema->rubros()->create([
            'nombre' => "Evaluación {$n}", 'semana' => $n, 'peso' => $pesos[$n - 1], 'orden' => $n, 'modo_calculo' => ModoCalculo::Promedio,
        ]));

        return [$e, $rubros];
    }

    private function practica(Curso $curso, int $numero): Tarea
    {
        return Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $curso->moduloPracticas()->id, 'practica' => $numero]);
    }

    /**
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @param  list<array{0: ?int, 1: ?int, 2: string}>  $filas
     * @return array<int, array{desde: string, hasta: string, peso: string}>
     */
    private function tramos($rubros, array $filas): array
    {
        return $rubros->values()->mapWithKeys(fn (RubroEvaluacion $rubro, int $i) => [$rubro->id => [
            'desde' => (string) ($filas[$i][0] ?? ''), 'hasta' => (string) ($filas[$i][1] ?? ''), 'peso' => $filas[$i][2] ?? '',
        ]])->all();
    }

    private function panel(array $e): Testable
    {
        return Livewire::actingAs($e['profesor'])->test(PracticasEvaluaciones::class, ['curso' => $e['curso']]);
    }

    private function calificar(Tarea $tarea, array $e, float $nota): void
    {
        Entrega::factory()->create(['tarea_id' => $tarea->id, 'estudiante_id' => $e['estudiante']->id, 'estado' => 'calificado', 'nota' => $nota]);
    }

    private function notaDe(RubroEvaluacion $rubro, array $e): ?float
    {
        return app(NotasDeEvaluacion::class)->resolver(collect([$rubro->fresh()]), collect([$e['estudiante']->id]))[$e['estudiante']->id][$rubro->id] ?? null;
    }
}
