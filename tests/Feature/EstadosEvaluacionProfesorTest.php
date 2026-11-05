<?php

namespace Tests\Feature;

use App\Enums\ModoCalculo;
use App\Livewire\Cursos\Actividades;
use App\Livewire\Quizzes\Formulario as FormularioQuiz;
use App\Livewire\Tareas\Formulario as FormularioTarea;
use App\Models\Entrega;
use App\Models\NotaRubro;
use App\Models\Quiz;
use App\Models\ResultadoQuiz;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Fase 0: la tarjeta de cada evaluación en Actividades dice en qué está
 * (Falta definir / En proceso (faltan X de Y) / Lista) y los formularios del
 * Profesor usan los componentes propios y textos que dicen la verdad.
 */
class EstadosEvaluacionProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_una_evaluacion_sin_actividades_falta_definir(): void
    {
        $e = $this->escenario();
        $this->esquemaValido($e['curso']);

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->assertSee('Falta definir')
            ->assertSee('Aún no hay actividades que cuenten para esta evaluación.')
            ->assertSee('Asignar actividades')
            ->assertSee('0 de 2 evaluaciones listas')
            ->assertDontSee('Sin configurar')
            ->assertDontSee('Todavía no elegiste cómo se calcula');
    }

    public function test_lo_pendiente_deja_la_evaluacion_en_proceso_y_al_completar_queda_lista(): void
    {
        $e = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();
        $otro = $this->usuarioConRol('estudiante');
        $this->matricular($otro, $e['curso']);
        $rubro->update(['modo_calculo' => ModoCalculo::Promedio]);
        $rubro->tareas()->attach($e['tarea']->id, ['peso' => 50]);
        $rubro->quizzes()->attach($e['quiz']->id, ['peso' => 50]);

        // Una entrega calificada (con 0) y otra solo entregada: el cero cuenta, lo entregado sin nota no.
        Entrega::factory()->create(['tarea_id' => $e['tarea']->id, 'estudiante_id' => $e['estudiante']->id, 'estado' => 'calificado', 'nota' => 0]);
        Entrega::factory()->create(['tarea_id' => $e['tarea']->id, 'estudiante_id' => $otro->id, 'estado' => 'entregado']);
        ResultadoQuiz::factory()->create(['quiz_id' => $e['quiz']->id, 'estudiante_id' => $e['estudiante']->id, 'puntaje' => 15, 'intento' => 1]);

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->assertSee('En proceso (faltan 2 de 4)')
            ->assertSee('Mientras falten no se cuentan como 0');

        Entrega::where('estudiante_id', $otro->id)->update(['estado' => 'calificado', 'nota' => 12]);
        ResultadoQuiz::factory()->create(['quiz_id' => $e['quiz']->id, 'estudiante_id' => $otro->id, 'puntaje' => 10, 'intento' => 1]);

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->assertSee('Todos los alumnos tienen nota.')
            ->assertSee('1 de 2 evaluaciones listas');
    }

    public function test_una_evaluacion_manual_cuenta_las_notas_registradas(): void
    {
        $e = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();
        $rubro->update(['modo_calculo' => ModoCalculo::Manual]);

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->assertSee('En proceso (faltan 1 de 1)')
            ->assertSee('Regístralas en el libro de calificaciones.');

        NotaRubro::create(['rubro_id' => $rubro->id, 'estudiante_id' => $e['estudiante']->id, 'nota' => 14]);

        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']])
            ->assertSee('1 de 2 evaluaciones listas');
    }

    public function test_las_consultas_no_crecen_con_las_evaluaciones_ni_las_actividades(): void
    {
        $e = $this->escenario();
        $esquema = $this->esquemaValido($e['curso']);
        [$rubro, $manual] = [$esquema->rubros[0], $esquema->rubros[1]];
        $rubro->update(['modo_calculo' => ModoCalculo::Promedio]);
        $manual->update(['modo_calculo' => ModoCalculo::Manual]);
        $rubro->tareas()->attach($e['tarea']->id, ['peso' => 50]);
        $rubro->quizzes()->attach($e['quiz']->id, ['peso' => 50]);

        // La primera visita carga permisos y configuración (luego quedan en caché): no se mide.
        $this->consultasAlMostrar($e);
        $pocas = $this->consultasAlMostrar($e);

        $tareas = Tarea::factory()->count(3)->create(['curso_id' => $e['curso']->id, 'modulo_id' => $e['modulo']->id]);
        $quizzes = Quiz::factory()->count(3)->create(['curso_id' => $e['curso']->id, 'modulo_id' => $e['modulo']->id]);
        $rubro->tareas()->sync($tareas->concat([$e['tarea']])->mapWithKeys(fn ($t) => [$t->id => ['peso' => 12.5]])->all());
        $rubro->quizzes()->sync($quizzes->concat([$e['quiz']])->mapWithKeys(fn ($q) => [$q->id => ['peso' => 12.5]])->all());
        foreach (range(1, 4) as $_) {
            $this->matricular($this->usuarioConRol('estudiante'), $e['curso']);
        }

        $this->assertSame($pocas, $this->consultasAlMostrar($e));
    }

    public function test_los_formularios_del_profesor_usan_desplegables_propios_y_textos_ciertos(): void
    {
        $e = $this->escenario();

        $tarea = Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso']])
            ->set('estado_contenido', 'programado')->set('tipo_entrega', 'grupal');
        $quiz = Livewire::actingAs($e['profesor'])->test(FormularioQuiz::class, ['curso' => $e['curso']])
            ->set('estado_contenido', 'programado');

        foreach ([$tarea, $quiz] as $formulario) {
            $html = $formulario->html();
            $this->assertStringNotContainsString('<select', $html);
            $this->assertStringNotContainsString('datetime-local', $html);
            $this->assertStringContainsString('role="listbox"', $html);
            $this->assertStringNotContainsString('no entra en la nota final ponderada', $html);
        }
        $tarea->assertSee('Si esta tarea está asignada a una evaluación');
        $quiz->assertSee('Si este quiz está asignado a una evaluación');
    }

    /**
     * @param  array<string, mixed>  $e
     */
    private function consultasAlMostrar(array $e): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::actingAs($e['profesor'])->test(Actividades::class, ['curso' => $e['curso']->fresh()]);
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }
}
