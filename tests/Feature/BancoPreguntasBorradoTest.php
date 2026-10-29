<?php

namespace Tests\Feature;

use App\Livewire\Quizzes\BancoPreguntas;
use App\Livewire\Quizzes\Formulario as FormularioQuiz;
use App\Models\BancoPregunta;
use App\Models\Curso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Borrado en el Banco de preguntas. Reproduce el caso reportado: con 7 de T1
 * y 20 sin unidad, la primera página (15) es 7 + 8; al pasar a otra unidad,
 * "Seleccionar esta página" usaba los ids de la PRIMERA carga (guardados en
 * Alpine) y "Eliminar seleccionadas" borraba preguntas que ya no se veían.
 */
class BancoPreguntasBorradoTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_seleccionar_esta_pagina_toma_solo_lo_visible_con_los_filtros_actuales(): void
    {
        $escenario = $this->escenario();
        $banco = $this->bancoReportado($escenario['curso']);

        $componente = $this->componente($escenario)
            ->set('unidadActual', 'T2')
            ->call('alternarSeleccionPagina');

        // Página 1 de T2: sus 8 preguntas y 7 sin unidad. Nada de T1.
        $seleccion = $componente->get('seleccionadas');
        $this->assertCount(15, $seleccion);
        $this->assertSame([], array_intersect($seleccion, $banco['T1']));
        $this->assertSame([], array_diff($banco['T2'], $seleccion));

        $componente->call('confirmarEliminarSeleccionadas')
            ->assertSee('Se eliminarán 15 preguntas')
            ->call('eliminarSeleccionadas')
            ->assertSet('seleccionadas', [])
            ->assertSee('Se eliminaron 15 preguntas del banco');

        $this->assertSame(7, BancoPregunta::whereIn('id', $banco['T1'])->count(), 'T1 no se toca');
        $this->assertSame(5, BancoPregunta::whereIn('id', $banco['T3'])->count());
        $this->assertSame(13, BancoPregunta::whereIn('id', $banco['sin'])->count());

        // Volver a pulsarla desmarca la página.
        $componente->call('alternarSeleccionPagina')->call('alternarSeleccionPagina')->assertSet('seleccionadas', []);
    }

    public function test_la_seleccion_se_limpia_al_cambiar_unidad_filtros_busqueda_o_pagina(): void
    {
        $escenario = $this->escenario();
        $this->bancoReportado($escenario['curso']);

        foreach ([
            ['unidadActual', 'T2'], ['verArchivadas', true], ['filtroTema', 'Tema T1'], ['filtroTipo', 'opcion_unica'], ['busqueda', 'Pregunta'],
        ] as [$propiedad, $valor]) {
            $this->componente($escenario)->call('alternarSeleccionPagina')
                ->assertCount('seleccionadas', 15)
                ->set($propiedad, $valor)
                ->assertSet('seleccionadas', []);
        }

        $this->componente($escenario)->call('alternarSeleccionPagina')
            ->call('nextPage')
            ->assertSet('seleccionadas', []);
    }

    public function test_solo_se_borran_ids_del_curso_que_estan_a_la_vista(): void
    {
        $escenario = $this->escenario();
        $otro = $this->escenario();
        $banco = $this->bancoReportado($escenario['curso']);
        $ajena = $this->pregunta($otro['curso'], 'T1', 'Ajena');

        // Ids manipulados: una de otra página (sin unidad, página 2), una de otra unidad (T3) y una de otro curso.
        $componente = $this->componente($escenario)
            ->set('seleccionadas', [$banco['T1'][0], $banco['sin'][19], $banco['T3'][0], $ajena->id])
            ->call('confirmarEliminarSeleccionadas')
            ->assertSee('Se eliminará 1 pregunta')
            ->call('eliminarSeleccionadas');

        $this->assertNull(BancoPregunta::find($banco['T1'][0]));
        $this->assertNotNull(BancoPregunta::find($banco['sin'][19]));
        $this->assertNotNull(BancoPregunta::find($banco['T3'][0]));
        $this->assertNotNull($ajena->fresh());
        $componente->assertSeeText('26 preguntas en la unidad actual');
    }

    public function test_eliminar_una_actualiza_la_lista_y_vuelve_de_una_pagina_vacia(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $preguntas = collect(range(1, 16))->map(fn ($i) => $this->pregunta($curso, 'T1', "Pregunta {$i}"));

        // La página 2 tiene una sola pregunta (la más antigua): al borrarla se vuelve a la página 1.
        $this->componente($escenario)
            ->call('nextPage')
            ->assertSee('Pregunta 1')
            ->call('eliminar', $preguntas[0]->id)
            ->assertSee('Pregunta eliminada del banco')
            ->assertSee('Pregunta 16')
            ->assertSeeText('15 preguntas en la unidad actual');

        $this->assertSame(15, $curso->bancoPreguntasCurso()->count());
    }

    public function test_borrar_del_banco_no_cambia_los_quizzes_que_la_copiaron(): void
    {
        $escenario = $this->escenario();
        $pregunta = $this->pregunta($escenario['curso'], 'T1', '¿Cuánto es 2 + 2?');
        $otra = $this->pregunta($escenario['curso'], 'T1', '¿Cuánto es 3 + 3?');

        Livewire::actingAs($escenario['profesor'])->test(FormularioQuiz::class, ['curso' => $escenario['curso'], 'quiz' => $escenario['quiz']])
            ->set('seleccionBanco', [$pregunta->id, $otra->id])
            ->call('agregarDesdeBanco')
            ->call('guardar')
            ->assertHasNoErrors();
        $antes = $escenario['quiz']->fresh()->preguntas;

        $this->componente($escenario)->call('eliminar', $pregunta->id)
            ->set('seleccionadas', [$otra->id])->call('confirmarEliminarSeleccionadas')->call('eliminarSeleccionadas');

        $this->assertSame(0, $escenario['curso']->bancoPreguntasCurso()->count());
        $this->assertSame($antes, $escenario['quiz']->fresh()->preguntas);
        $this->assertContains('¿Cuánto es 2 + 2?', array_column($antes, 'enunciado'));
    }

    public function test_el_banco_contiene_sus_textos_ocultos_para_no_estirar_la_pagina(): void
    {
        $escenario = $this->escenario();
        $this->pregunta($escenario['curso'], 'T1', 'Pregunta');

        // Los sr-only (position: absolute) necesitan un ancestro posicionado dentro de la columna con scroll.
        $this->assertMatchesRegularExpression('/^\s*(<!--.*?-->\s*)*<div[^>]*class="relative/s', $this->componente($escenario)->html());
    }

    /**
     * El banco del reporte: T1:7, T2:8, T3:5 y 20 sin unidad (ids por grupo, del más nuevo al más antiguo).
     *
     * @return array{T1: list<int>, T2: list<int>, T3: list<int>, sin: list<int>}
     */
    private function bancoReportado(Curso $curso): array
    {
        $ids = [];
        foreach (['T1' => 7, 'T2' => 8, 'T3' => 5, 'sin' => 20] as $grupo => $cantidad) {
            foreach (range(1, $cantidad) as $i) {
                $ids[$grupo][] = $this->pregunta($curso, $grupo === 'sin' ? null : $grupo, "Pregunta {$grupo} {$i}")->id;
            }
            $ids[$grupo] = array_reverse($ids[$grupo]);
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $escenario
     */
    private function componente(array $escenario): Testable
    {
        return Livewire::actingAs($escenario['profesor'])->test(BancoPreguntas::class, ['curso' => $escenario['curso'], 'incrustado' => true]);
    }

    private function pregunta(Curso $curso, ?string $unidad, string $texto): BancoPregunta
    {
        return $curso->bancoPreguntasCurso()->create(['unidad' => $unidad, 'tema' => 'Tema '.($unidad ?? 'X'), 'pregunta' => $texto, 'tipo' => 'opcion_unica', 'opciones' => ['A', 'B'], 'respuestas_correctas' => [0]]);
    }
}
