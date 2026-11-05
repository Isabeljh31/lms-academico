<?php

namespace Tests\Feature;

use App\Livewire\EventoCalendario\Index as Calendario;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Calendario del Profesor: vista por semana y bloque "Actividades de la
 * semana por curso". Las actividades se ubican en la semana con los datos que
 * entrega el servidor (fecha de cada actividad, o la semana de su módulo si no
 * tiene fecha); el Estudiante y el Supervisor no reciben nada de esto.
 */
class CalendarioSemanaProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    public function test_el_profesor_ve_el_bloque_de_actividades_de_la_semana_por_curso(): void
    {
        $escenario = $this->escenario();

        $this->actingAs($escenario['profesor'])
            ->get(route('evento-calendario.index'))
            ->assertOk()
            ->assertSee('Actividades de la semana por curso')
            ->assertSee('Sin actividades esta semana.');
    }

    public function test_las_actividades_con_fecha_traen_titulo_fecha_corta_estado_y_por_calificar(): void
    {
        $escenario = $this->escenario();
        $entrega = Carbon::parse('2026-10-28 09:00');
        $escenario['tarea']->update(['titulo' => 'Informe de laboratorio', 'fecha_entrega' => $entrega]);
        Entrega::create(['tarea_id' => $escenario['tarea']->id, 'estudiante_id' => $escenario['estudiante']->id, 'estado' => 'entregado', 'respuesta_texto' => 'Listo']);

        $calendario = Livewire::actingAs($escenario['profesor'])->test(Calendario::class)->instance();
        $tarea = collect($calendario->eventos)->firstWhere('id', 'tarea-'.$escenario['tarea']->id);

        $this->assertSame('2026-10-28T09:00:00', $tarea['start']);
        $this->assertSame('Informe de laboratorio', $tarea['extendedProps']['titulo']);
        $this->assertSame($entrega->translatedFormat('D d').' · 09:00', $tarea['extendedProps']['fechaCorta']);
        $this->assertSame($escenario['tarea']->etiquetaEstadoPublicacion(), $tarea['extendedProps']['estado']);
        $this->assertSame(1, $tarea['extendedProps']['porCalificar']);
    }

    public function test_quizzes_sin_fecha_y_evaluaciones_se_ubican_en_su_semana_academica(): void
    {
        $escenario = $this->escenario();
        // Miércoles: la Semana 1 empieza el lunes 31 de agosto.
        $escenario['curso']->update(['fecha_inicio' => '2026-09-02', 'semanas_duracion' => 12]);
        $semana3 = Modulo::factory()->create(['curso_id' => $escenario['curso']->id, 'semana' => 3]);
        $sinFecha = Quiz::factory()->create(['curso_id' => $escenario['curso']->id, 'modulo_id' => $semana3->id, 'tema' => 'Repaso sin fecha', 'fecha_limite' => null]);
        $this->esquemaValido($escenario['curso']);

        $calendario = Livewire::actingAs($escenario['profesor'])->test(Calendario::class)->instance();

        $quiz = collect($calendario->semanaPorCurso['sinFecha'])->firstWhere('id', 'quiz-'.$sinFecha->id);
        $this->assertSame('2026-09-14', $quiz['inicioSemana']);
        $this->assertSame('Repaso sin fecha', $quiz['titulo']);
        $this->assertSame(route('cursos.quizzes.show', [$escenario['curso'], $sinFecha]), $quiz['url']);
        // Un quiz sin fecha no va a la grilla (no tiene día).
        $this->assertNull(collect($calendario->eventos)->firstWhere('id', 'quiz-'.$sinFecha->id));

        $parcial = collect($calendario->eventos)->first(fn ($e) => ($e['extendedProps']['titulo'] ?? null) === 'Parcial');
        $this->assertSame('2026-09-21', $parcial['start']);
        $this->assertSame('evaluacion', $parcial['extendedProps']['tipo']);

        $curso = collect($calendario->semanaPorCurso['cursos'])->firstWhere('id', $escenario['curso']->id);
        $this->assertSame('2026-08-31', $curso['inicio']);
        $this->assertSame(12, $curso['totalSemanas']);
    }

    public function test_el_total_de_semanas_incluye_contenido_ubicado_despues_de_la_duracion(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['fecha_inicio' => '2026-09-02', 'semanas_duracion' => 4]);
        Modulo::factory()->create(['curso_id' => $escenario['curso']->id, 'semana' => 6]);

        $calendario = Livewire::actingAs($escenario['profesor'])->test(Calendario::class)->instance();
        $curso = collect($calendario->semanaPorCurso['cursos'])->firstWhere('id', $escenario['curso']->id);

        $this->assertSame($escenario['curso']->fresh()->totalSemanas(), $curso['totalSemanas']);
        $this->assertSame(6, $curso['totalSemanas']);
    }

    public function test_agrupa_por_cada_curso_del_profesor_y_no_incluye_cursos_ajenos(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['fecha_inicio' => '2026-09-02']);
        $propio = Curso::factory()->create(['profesor_id' => $escenario['profesor']->id, 'nombre' => 'Segundo curso', 'fecha_inicio' => '2026-09-02']);
        $ajeno = Curso::factory()->create(['profesor_id' => $this->usuarioConRol('profesor')->id, 'nombre' => 'Curso de otro docente', 'fecha_inicio' => '2026-09-02']);
        $moduloAjeno = Modulo::factory()->create(['curso_id' => $ajeno->id, 'semana' => 2]);
        Quiz::factory()->create(['curso_id' => $ajeno->id, 'modulo_id' => $moduloAjeno->id, 'fecha_limite' => null]);
        Tarea::factory()->create(['curso_id' => $ajeno->id, 'modulo_id' => $moduloAjeno->id, 'fecha_entrega' => now()]);

        $calendario = Livewire::actingAs($escenario['profesor'])->test(Calendario::class)
            ->assertDontSee('Curso de otro docente')
            ->instance();

        $this->assertEqualsCanonicalizing([$escenario['curso']->id, $propio->id], collect($calendario->semanaPorCurso['cursos'])->pluck('id')->all());
        $this->assertNotContains($ajeno->id, collect($calendario->semanaPorCurso['sinFecha'])->pluck('cursoId')->all());
        $this->assertNotContains($ajeno->id, collect($calendario->eventos)->pluck('extendedProps.cursoId')->all());
        // Cada curso con su color propio, el mismo de "Mis cursos".
        $this->assertSame($calendario->coloresCursos[$propio->id], collect($calendario->semanaPorCurso['cursos'])->firstWhere('id', $propio->id)['color']);
    }

    public function test_el_estudiante_conserva_su_calendario_sin_el_bloque_semanal(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['fecha_inicio' => '2026-09-02']);

        $this->actingAs($escenario['estudiante'])
            ->get(route('evento-calendario.index'))
            ->assertOk()
            ->assertSee('Por entregar esta semana')
            ->assertDontSee('Actividades de la semana por curso');

        $calendario = Livewire::actingAs($escenario['estudiante'])->test(Calendario::class)->instance();
        $tarea = collect($calendario->eventos)->firstWhere('id', 'tarea-'.$escenario['tarea']->id);

        $this->assertNull($calendario->semanaPorCurso);
        $this->assertSame(['tipo', 'propio', 'cursoId', 'porCalificar'], array_keys($tarea['extendedProps']));
        $this->assertNull(collect($calendario->eventos)->firstWhere('id', 'quiz-'.$escenario['quiz']->id));
    }

    /**
     * Regresión: unas comillas dobles dentro del JavaScript del calendario
     * cerraban el atributo x-data antes de tiempo y el calendario quedaba
     * vacío para todos los roles.
     */
    public function test_el_codigo_del_calendario_llega_completo_para_profesor_y_estudiante(): void
    {
        $escenario = $this->escenario();

        foreach (['profesor', 'estudiante'] as $rol) {
            $html = $this->actingAs($escenario[$rol])->get(route('evento-calendario.index'))->assertOk()->getContent();
            $documento = new \DOMDocument;
            @$documento->loadHTML('<?xml encoding="utf-8"?>'.$html);
            $calendario = collect(iterator_to_array($documento->getElementsByTagName('div')))
                ->map(fn (\DOMElement $div) => $div->getAttribute('x-data'))
                ->first(fn (string $codigo) => str_contains($codigo, 'calendar: null'));

            $this->assertNotNull($calendario, "Sin x-data del calendario ({$rol}).");
            $this->assertStringContainsString('claseEstado(estado)', $calendario, "x-data del calendario cortado ({$rol}).");
        }
    }

    public function test_el_supervisor_no_recibe_el_bloque_semanal(): void
    {
        $escenario = $this->escenario();

        $calendario = Livewire::actingAs($escenario['supervisor'])->test(Calendario::class)
            ->assertDontSee('Actividades de la semana por curso')
            ->instance();

        $this->assertNull($calendario->semanaPorCurso);
    }
}
