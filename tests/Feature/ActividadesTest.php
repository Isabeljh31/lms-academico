<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Compartido\Quizzes\Formulario as FormularioQuiz;
use App\Livewire\Compartido\Quizzes\Resolver;
use App\Livewire\Compartido\Tareas\Formulario as FormularioTarea;
use App\Livewire\Profesor\Actividades;
use App\Livewire\Profesor\Evaluacion;
use App\Models\Entrega;
use App\Models\Quiz;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Apartado "Actividades" del Profesor del curso: tareas y quizzes en una
 * lista unificada y el banco de preguntas en otra pestaña. Las pantallas de
 * Tareas y Quizzes del Estudiante no cambian.
 */
class ActividadesTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_el_curso_del_profesor_tiene_un_solo_boton_actividades(): void
    {
        $escenario = $this->escenario();

        $this->actingAs($escenario['profesor'])->get(route('cursos.show', $escenario['curso']))
            ->assertOk()
            ->assertSee(route('cursos.actividades', $escenario['curso']), false)
            ->assertDontSee(route('cursos.tareas.index', $escenario['curso']).'"', false)
            ->assertDontSee(route('cursos.quizzes.index', $escenario['curso']).'"', false)
            ->assertDontSee(route('cursos.banco-preguntas', $escenario['curso']).'"', false);
    }

    public function test_la_lista_unifica_tareas_y_quizzes_con_filtros_y_busqueda(): void
    {
        $escenario = $this->escenario();
        $escenario['tarea']->update(['titulo' => 'Ensayo de lógica']);
        $escenario['quiz']->update(['tema' => 'Quiz de conjuntos']);
        Entrega::create(['tarea_id' => $escenario['tarea']->id, 'estudiante_id' => $escenario['estudiante']->id, 'estado' => 'entregado']);

        $this->componente($escenario)
            ->assertSee('Ensayo de lógica')
            ->assertSee('Quiz de conjuntos')
            // Estado de la fila: lo que falta calificar enlaza a «Por calificar» de esa tarea.
            ->assertSeeText('1 por calificar')
            ->assertSee(route('docente.por-calificar', ['tarea' => $escenario['tarea']->id]), false)
            ->assertSee('0 intentos')
            ->call('filtrar', 'tareas')
            ->assertSet('tipo', 'tareas')
            ->assertSee('Ensayo de lógica')
            ->assertDontSee('Quiz de conjuntos')
            ->call('filtrar', 'quizzes')
            ->assertDontSee('Ensayo de lógica')
            ->assertSee('Quiz de conjuntos')
            ->call('filtrar', 'todas')
            ->set('busqueda', 'conjuntos')
            ->assertDontSee('Ensayo de lógica')
            ->assertSee('Quiz de conjuntos')
            // Un filtro que no existe vuelve a "todas".
            ->call('filtrar', 'otra')
            ->assertSet('tipo', 'todas');
    }

    public function test_la_pestana_y_los_filtros_sobreviven_a_un_re_render(): void
    {
        $escenario = $this->escenario();

        $componente = $this->componente($escenario)
            ->set('pestana', 'banco')
            ->call('filtrar', 'quizzes')
            ->set('busqueda', 'Quiz')
            ->call('$refresh')
            ->assertSet('pestana', 'banco')
            ->assertSet('tipo', 'quizzes')
            ->assertSet('busqueda', 'Quiz');

        $this->assertMatchesRegularExpression('/id="panel-banco"[^>]*x-show/', $componente->html());
        $this->assertDoesNotMatchRegularExpression('/id="panel-banco"[^>]*display: none/', $componente->html());
        $this->assertMatchesRegularExpression('/id="panel-actividades"[^>]*display: none/', $componente->html());

        // También desde la URL (?pestana=banco).
        $this->actingAs($escenario['profesor'])->get(route('cursos.actividades', [$escenario['curso'], 'pestana' => 'banco']))
            ->assertOk()
            ->assertSee('aria-selected="true"', false);
    }

    public function test_el_menu_crear_actividad_lleva_a_los_formularios_existentes(): void
    {
        $escenario = $this->escenario();

        $this->componente($escenario)
            ->assertSee('Crear actividad')
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee(e(route('cursos.tareas.crear', [$escenario['curso'], 'desde' => 'actividades'])), false)
            ->assertSee(e(route('cursos.quizzes.crear', [$escenario['curso'], 'desde' => 'actividades'])), false);

        // Abiertos desde Actividades, guardar o cancelar vuelve a Actividades; sin el parámetro, como antes.
        Livewire::actingAs($escenario['profesor'])
            ->test(FormularioQuiz::class, ['curso' => $escenario['curso']])
            ->assertSet('desdeActividades', false)
            ->assertSee(route('cursos.quizzes.index', $escenario['curso']), false);
        Livewire::withQueryParams(['desde' => 'actividades'])->actingAs($escenario['profesor'])
            ->test(FormularioTarea::class, ['curso' => $escenario['curso']])
            ->assertSet('desdeActividades', true)
            ->assertSee(e(route('cursos.actividades', [$escenario['curso'], 'tipo' => 'tareas'])), false);
    }

    public function test_eliminar_pide_confirmacion_y_no_se_ofrece_si_hay_entregas(): void
    {
        $escenario = $this->escenario();
        $conEntrega = Tarea::factory()->create(['curso_id' => $escenario['curso']->id, 'modulo_id' => $escenario['modulo']->id, 'titulo' => 'Con entrega']);
        Entrega::create(['tarea_id' => $conEntrega->id, 'estudiante_id' => $escenario['estudiante']->id, 'estado' => 'entregado']);

        $componente = $this->componente($escenario)
            ->call('confirmarEliminar', 'quiz', $escenario['quiz']->id)
            ->assertSet('mostrarConfirmacion', true)
            ->assertSee('Se eliminará el quiz');
        $this->assertNotNull($escenario['quiz']->fresh());

        $componente->call('eliminar')->assertSet('mostrarConfirmacion', false);
        $this->assertNull($escenario['quiz']->fresh());

        $componente->assertSee('Tiene entregas: no se puede eliminar', false)
            ->call('confirmarEliminar', 'tarea', $conEntrega->id)
            ->assertForbidden();
        $this->assertNotNull($conEntrega->fresh());
    }

    public function test_no_se_elimina_una_actividad_de_otro_curso(): void
    {
        $escenario = $this->escenario();
        $otro = $this->escenario();

        $this->componente($escenario)->call('confirmarEliminar', 'tarea', $otro['tarea']->id)->assertNotFound();
        $this->assertNotNull($otro['tarea']->fresh());
    }

    public function test_solo_el_profesor_del_curso_entra_a_actividades(): void
    {
        $escenario = $this->escenario();
        $otroProfesor = $this->usuarioConRol(Rol::Profesor->value);
        $url = route('cursos.actividades', $escenario['curso']);

        $this->actingAs($escenario['profesor'])->get($url)->assertOk()->assertSee('Banco de preguntas');
        $this->actingAs($otroProfesor)->get($url)->assertForbidden();
        $this->actingAs($escenario['estudiante'])->get($url)->assertForbidden();
        $this->actingAs($escenario['supervisor'])->get($url)->assertForbidden();
    }

    public function test_la_pantalla_evaluacion_es_solo_del_profesor_del_curso(): void
    {
        $escenario = $this->escenario();
        $url = route('cursos.evaluacion', $escenario['curso']);

        $this->actingAs($escenario['profesor'])->get($url)->assertOk()
            ->assertSee('Cómo se calcula la nota final del curso')
            // Barra de pestañas del curso con la actual marcada.
            ->assertSeeInOrder(['aria-label="Secciones del curso"', 'aria-current="page"', 'Evaluación'], false);
        $this->actingAs($this->usuarioConRol(Rol::Profesor->value))->get($url)->assertForbidden();
        $this->actingAs($escenario['estudiante'])->get($url)->assertForbidden();
    }

    public function test_la_lista_se_filtra_por_evaluacion_y_cada_tarjeta_enlaza_a_sus_actividades(): void
    {
        $escenario = $this->escenario();
        $escenario['tarea']->update(['titulo' => 'Ensayo de lógica']);
        $escenario['quiz']->update(['tema' => 'Quiz de conjuntos']);
        $rubro = $this->esquemaValido($escenario['curso'])->rubros->first();
        $rubro->tareas()->attach($escenario['tarea']->id, ['peso' => 100]);

        $this->componente($escenario)
            ->assertSee('Quiz de conjuntos')
            ->set('filtroEvaluacion', (string) $rubro->id)
            ->assertSee('Ensayo de lógica')
            ->assertDontSee('Quiz de conjuntos')
            // Un valor que no es un id vuelve a «Todas».
            ->set('filtroEvaluacion', 'x')
            ->assertSet('filtroEvaluacion', '');

        Livewire::actingAs($escenario['profesor'])->test(Evaluacion::class, ['curso' => $escenario['curso']])
            ->assertSee('Ver actividades (1)')
            ->assertSee(route('cursos.actividades', [$escenario['curso'], 'evaluacion' => $rubro->id]), false);
    }

    public function test_cada_fila_muestra_cuanto_falta_para_el_cierre_o_que_esta_al_dia(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(10, 0));
        $escenario = $this->escenario();
        $escenario['tarea']->update(['fecha_entrega' => now()->setDate(2026, 12, 11)->setTime(23, 59)]);
        $escenario['quiz']->update(['fecha_limite' => now()->subDay()]);

        // Días de calendario, como en el mockup: del 23/11 al 11/12 son 18.
        $this->componente($escenario)
            ->assertSee('Vence en 18 días')
            ->assertSee('Al día');

        $escenario['tarea']->update(['fecha_entrega' => now()->setTime(23, 59)]);
        $this->componente($escenario)->assertSee('Vence hoy');
    }

    public function test_la_lista_se_carga_sin_consultas_repetidas_y_con_paginacion(): void
    {
        $escenario = $this->escenario();

        $consultasCon = function (int $cantidad) use ($escenario): int {
            Tarea::factory()->count($cantidad)->create(['curso_id' => $escenario['curso']->id, 'modulo_id' => $escenario['modulo']->id]);
            Quiz::factory()->count($cantidad)->create(['curso_id' => $escenario['curso']->id, 'modulo_id' => $escenario['modulo']->id]);

            $medir = fn () => Livewire::actingAs($escenario['profesor'])->test(Actividades::class, ['curso' => $escenario['curso']->fresh()]);
            $medir();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $medir();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->assertSame($consultasCon(1), $consultasCon(8));

        // 21 actividades (las 2 del escenario + 9 tareas + 9 quizzes + 1 más): 10 por página.
        Tarea::factory()->create(['curso_id' => $escenario['curso']->id, 'modulo_id' => $escenario['modulo']->id]);
        $componente = $this->componente($escenario);
        $this->assertSame(10, substr_count($componente->html(), 'wire:key="actividad-'));
        $componente->call('setPage', 3, 'pagina');
        $this->assertSame(1, substr_count($componente->html(), 'wire:key="actividad-'));
    }

    public function test_tareas_y_quizzes_del_estudiante_siguen_igual(): void
    {
        $escenario = $this->escenario();
        $quiz = $escenario['quiz'];
        $quiz->update(['preguntas' => [
            ['enunciado' => '¿2 + 2?', 'tipo' => 'opcion_unica', 'opciones' => ['3', '4'], 'respuesta_correcta' => 1, 'respuestas_correctas' => [1], 'puntaje' => 1, 'retroalimentacion' => 'Suma básica.'],
        ]]);

        $this->actingAs($escenario['estudiante'])->get(route('cursos.tareas.index', $escenario['curso']))
            ->assertOk()->assertSee($escenario['tarea']->titulo)->assertDontSee('Crear actividad');
        $this->actingAs($escenario['estudiante'])->get(route('cursos.quizzes.index', $escenario['curso']))
            ->assertOk()->assertSee($quiz->tema)->assertDontSee('Crear actividad');
        $this->actingAs($escenario['estudiante'])->get(route('cursos.show', $escenario['curso']))
            ->assertOk()->assertDontSee(route('cursos.actividades', $escenario['curso']), false);

        // Resuelve y ve su nota y la retroalimentación al terminar.
        Livewire::actingAs($escenario['estudiante'])->test(Resolver::class, ['quiz' => $quiz])
            ->set('respuestas', [1])
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSee('/ 20')
            ->assertSee('Correcta')
            ->assertSee('Suma básica.');
    }

    /**
     * @param  array<string, mixed>  $escenario
     */
    private function componente(array $escenario): Testable
    {
        return Livewire::actingAs($escenario['profesor'])->test(Actividades::class, ['curso' => $escenario['curso']]);
    }
}
